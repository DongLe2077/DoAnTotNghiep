<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\MaintenanceRequest;
use App\Models\Occupancy;
use App\Models\Payment;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class MaintenanceManagementTest extends TestCase
{
    use CreatesCashBookSchema;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 5)->setTime(12, 0));
        $this->createCashBookSchema();
        $this->owner = User::factory()->create();
    }

    public function test_current_representative_discovers_their_occupancy_and_submits_a_report(): void
    {
        [$occupancy, $user, $representative] = $this->occupancy();
        $this->occupancy();
        $this->actingAs($user, 'web');
        $this->getJson('/api/tenant/maintenance-requests/eligible-occupancies')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.occupancy_id', $occupancy->id)->assertJsonPath('data.0.room.id', $occupancy->room_id);
        $response = $this->send($occupancy)->assertCreated()->assertJsonPath('replayed', false)
            ->assertJsonPath('data.occupancy_id', $occupancy->id)->assertJsonPath('data.submitted_by', $user->id)
            ->assertJsonPath('data.representative_assignment_id', $representative->id)
            ->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.status_label', 'Đã gửi')
            ->assertJsonPath('data.resolution', null)->assertJsonPath('data.can_process', false)
            ->assertJsonPath('data.submitted_at', '2026-03-05 12:00:00')
            ->assertJsonMissingPath('data.version_token')->assertJsonMissingPath('data.reporter')->assertJsonMissingPath('data.password_hash');
        $this->getJson('/api/tenant/maintenance-requests/'.$response->json('data.id'))->assertOk();
        $this->assertSame(1, MaintenanceRequest::count());
    }

    public function test_tenant_cannot_submit_for_other_rooms_or_without_being_current_representative(): void
    {
        [$occupancy, $representative] = $this->occupancy();
        [$other] = $this->occupancy();
        $this->actingAs($representative, 'web');
        $this->send($other)->assertNotFound();
        $this->postJson('/api/tenant/occupancies/999999/maintenance-requests', $this->payload())->assertNotFound();
        [, $member] = $this->member($occupancy);
        $this->actingAs($member, 'web');
        $this->getJson('/api/tenant/maintenance-requests/eligible-occupancies')->assertJsonCount(0, 'data');
        $this->send($occupancy)->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'tenant', 'tenant_id' => null]), 'web');
        $this->send($occupancy)->assertNotFound();
        $this->assertSame(0, MaintenanceRequest::count());
    }

    public function test_closed_or_not_yet_started_stay_and_future_representative_are_not_eligible(): void
    {
        [$occupancy, $user, $representative, $stay] = $this->occupancy();
        $this->actingAs($user, 'web');
        $stay->update(['moved_out_at' => '2026-02-01']);
        $this->send($occupancy)->assertNotFound();
        $stay->update(['moved_out_at' => null, 'moved_in_at' => '2026-03-06']);
        $this->send($occupancy)->assertNotFound();
        $stay->update(['moved_in_at' => '2026-01-01']);
        $representative->update(['started_at' => '2026-03-06']);
        $this->send($occupancy)->assertNotFound();
        $this->getJson('/api/tenant/maintenance-requests/eligible-occupancies')->assertJsonCount(0, 'data');
    }

    public function test_duplicate_open_report_returns_original_even_after_owner_starts_work(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $this->actingAs($user, 'web');
        $first = $this->send($occupancy)->assertCreated();
        $id = $first->json('data.id');
        $this->send($occupancy)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $id);
        $this->actingAs($this->owner, 'web');
        $current = $this->getJson('/api/owner/maintenance-requests/'.$id)->assertOk();
        $this->process($id, $current->json('data.version_token'), ['status' => 'in_progress', 'resolution' => 'Đã hẹn thợ'])->assertOk();
        $this->actingAs($user, 'web');
        $this->send($occupancy)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.status', 'in_progress');
        $this->assertSame(1, MaintenanceRequest::count());
    }

    public function test_similar_but_not_identical_report_is_not_silently_discarded(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $this->actingAs($user, 'web');
        $this->send($occupancy, ['title' => 'Vòi nước hỏng'])->assertCreated();
        $this->send($occupancy, ['title' => 'Voi nuoc hong'])->assertCreated();
        $this->send($occupancy, ['title' => 'Vòi nước hỏng', 'description' => 'Ống thoát nước cũng bị rò'])->assertCreated();
        $this->assertSame(3, MaintenanceRequest::count());
    }

    public function test_owner_processes_and_resolves_report_without_changing_original_submission(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $report = $this->report($occupancy, $user);
        $original = $report->only(['occupancy_id', 'submitted_by', 'representative_assignment_id', 'title', 'description']);
        $submittedAt = $report->submitted_at;
        $this->actingAs($this->owner, 'web');
        $current = $this->getJson('/api/owner/maintenance-requests/'.$report->id)->assertOk()
            ->assertJsonPath('data.can_process', true)->assertJsonPath('data.reporter.id', $user->tenant_id)
            ->assertJsonMissingPath('data.reporter.password_hash')->assertJsonMissingPath('data.reporter.username');
        $this->travel(1)->minutes();
        $progress = $this->process($report->id, $current->json('data.version_token'), ['status' => 'in_progress', 'resolution' => 'Thợ đang kiểm tra đường ống'])->assertOk()
            ->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.status_label', 'Đang xử lý');
        $this->travel(1)->minutes();
        $done = $this->process($report->id, $progress->json('data.version_token'), ['status' => 'resolved', 'resolution' => 'Đã thay gioăng, kiểm tra không còn rò nước'])->assertOk()
            ->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.status_label', 'Đã xử lý')->assertJsonPath('data.can_process', false);
        $this->assertSame($original, $report->fresh()->only(array_keys($original)));
        $this->assertTrue($submittedAt->equalTo($report->fresh()->submitted_at));
        $this->assertSame('2026-03-05 12:02:00', $done->json('data.updated_at'));
        $this->actingAs($user, 'web')->getJson('/api/tenant/maintenance-requests/'.$report->id)->assertOk()
            ->assertJsonPath('data.resolution', 'Đã thay gioăng, kiểm tra không còn rò nước');
        $this->assertSame(0, CashTransaction::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_progress_notes_can_be_updated_and_completed_directly_from_submitted(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $report = $this->report($occupancy, $user);
        $this->actingAs($this->owner, 'web');
        $current = $this->getJson('/api/owner/maintenance-requests/'.$report->id);
        $progress = $this->process($report->id, $current->json('data.version_token'), ['status' => 'in_progress'])->assertOk()->assertJsonPath('data.resolution', null);
        $updated = $this->process($report->id, $progress->json('data.version_token'), ['status' => 'in_progress', 'resolution' => 'Đang chờ linh kiện'])->assertOk();
        $this->process($report->id, $updated->json('data.version_token'), ['status' => 'in_progress', 'resolution' => null])->assertOk()->assertJsonPath('data.resolution', null);
        $second = $this->report($occupancy, $user, ['title' => 'Khóa cửa kẹt']);
        $this->actingAs($this->owner, 'web');
        $current = $this->getJson('/api/owner/maintenance-requests/'.$second->id);
        $this->process($second->id, $current->json('data.version_token'), ['status' => 'resolved', 'resolution' => 'Đã điều chỉnh ổ khóa'])->assertOk();
    }

    public function test_resolved_is_terminal_and_recurrent_fault_can_be_a_new_report(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $report = $this->report($occupancy, $user);
        $this->actingAs($this->owner, 'web');
        $current = $this->getJson('/api/owner/maintenance-requests/'.$report->id);
        $resolved = $this->process($report->id, $current->json('data.version_token'), ['status' => 'resolved', 'resolution' => 'Đã sửa'])->assertOk();
        $this->process($report->id, $resolved->json('data.version_token'), ['status' => 'in_progress'])->assertConflict();
        $this->process($report->id, $resolved->json('data.version_token'), ['status' => 'resolved', 'resolution' => 'Ghi đè kết quả'])->assertConflict();
        $this->actingAs($user, 'web');
        $next = $this->send($occupancy)->assertCreated();
        $this->assertNotSame($report->id, $next->json('data.id'));
        $this->assertSame(2, MaintenanceRequest::count());
    }

    public function test_stale_and_wrong_report_version_cannot_overwrite_progress(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $one = $this->report($occupancy, $user);
        $two = $this->report($occupancy, $user, ['title' => 'Mất điện']);
        $this->actingAs($this->owner, 'web');
        $token = $this->getJson('/api/owner/maintenance-requests/'.$one->id)->json('data.version_token');
        $updated = $this->process($one->id, $token, ['status' => 'in_progress', 'resolution' => 'Đã tiếp nhận'])->assertOk();
        $this->process($one->id, $token, ['status' => 'resolved', 'resolution' => 'Không được ghi đè'])->assertConflict();
        $this->process($two->id, $updated->json('data.version_token'), ['status' => 'in_progress'])->assertConflict();
        $this->assertSame('Đã tiếp nhận', $one->fresh()->resolution);
        $this->assertSame('submitted', $two->fresh()->status);
    }

    #[DataProvider('invalidReports')]
    public function test_invalid_or_forged_tenant_submission_is_rejected(array $change, string $field): void
    {
        [$occupancy, $user] = $this->occupancy();
        $this->actingAs($user, 'web');
        $this->send($occupancy, $change)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, MaintenanceRequest::count());
    }

    public static function invalidReports(): array
    {
        return [
            'empty title' => [['title' => ' '], 'title'],
            'long title' => [['title' => str_repeat('a', 151)], 'title'],
            'empty description' => [['description' => ' '], 'description'],
            'long description' => [['description' => str_repeat('a', 8001)], 'description'],
            'array description' => [['description' => ['fault']], 'description'],
            'occupancy spoof' => [['occupancy_id' => 999], 'occupancy_id'],
            'reporter spoof' => [['submitted_by' => 999], 'submitted_by'],
            'representative spoof' => [['representative_assignment_id' => 999], 'representative_assignment_id'],
            'status spoof' => [['status' => 'resolved'], 'status'],
            'resolution spoof' => [['resolution' => 'Đã xong'], 'resolution'],
            'submission date spoof' => [['submitted_at' => '2026-01-01'], 'submitted_at'],
            'updated date spoof' => [['updated_at' => '2026-01-01'], 'updated_at'],
        ];
    }

    public function test_utf8_text_limits_fit_the_mysql_title_and_text_columns(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $this->actingAs($user, 'web');
        $created = $this->send($occupancy, ['title' => str_repeat('🔧', 150), 'description' => str_repeat('🔧', 8000)])->assertCreated();
        $this->actingAs($this->owner, 'web');
        $token = $this->getJson('/api/owner/maintenance-requests/'.$created->json('data.id'))->json('data.version_token');
        $this->process($created->json('data.id'), $token, ['status' => 'resolved', 'resolution' => str_repeat('🔧', 8000)])->assertOk();
    }

    public function test_missing_fields_and_bad_owner_updates_are_rejected(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $this->actingAs($user, 'web');
        $this->postJson('/api/tenant/occupancies/'.$occupancy->id.'/maintenance-requests', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description']);
        $report = $this->report($occupancy, $user);
        $this->actingAs($this->owner, 'web');
        $token = $this->getJson('/api/owner/maintenance-requests/'.$report->id)->json('data.version_token');
        foreach ([[], ['status' => 'resolved'], ['status' => 'resolved', 'resolution' => ' '], ['status' => 'resolved', 'resolution' => null],
            ['status' => 'cancelled'], ['status' => 'submitted'], ['status' => 'in_progress', 'resolution' => str_repeat('a', 8001)]] as $values) {
            $this->process($report->id, $token, $values)->assertUnprocessable();
        }
        $this->patchJson('/api/owner/maintenance-requests/'.$report->id, ['status' => 'in_progress'])->assertUnprocessable()->assertJsonValidationErrors('version_token');
        foreach (['title', 'description', 'occupancy_id', 'submitted_by', 'representative_assignment_id', 'submitted_at', 'updated_at'] as $field) {
            $this->process($report->id, $token, ['status' => 'in_progress', $field => 'forged'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame('submitted', $report->fresh()->status);
    }

    public function test_tenant_only_reads_their_own_reports_and_filters_cannot_expand_access(): void
    {
        [$ownOccupancy, $user] = $this->occupancy();
        [$otherOccupancy, $otherUser] = $this->occupancy();
        $own = $this->report($ownOccupancy, $user);
        $other = $this->report($otherOccupancy, $otherUser, ['title' => 'Riêng tư nhóm khác']);
        $this->actingAs($user, 'web');
        $this->getJson('/api/tenant/maintenance-requests')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/tenant/maintenance-requests/'.$other->id)->assertNotFound();
        $this->getJson('/api/tenant/maintenance-requests/999999')->assertNotFound();
        $this->getJson('/api/tenant/maintenance-requests?occupancy_id='.$otherOccupancy->id)->assertJsonCount(0, 'data');
        $this->getJson('/api/tenant/maintenance-requests?submitted_by='.$otherUser->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/tenant/maintenance-requests?q='.rawurlencode('Riêng tư nhóm khác'))->assertJsonCount(0, 'data');
        $this->getJson('/api/owner/maintenance-requests/'.$own->id)->assertForbidden();
        $this->patchJson('/api/owner/maintenance-requests/999999', [])->assertForbidden();
    }

    public function test_owner_filters_pagination_and_search_keep_correct_scope(): void
    {
        [$one, $user] = $this->occupancy();
        [$two, $other] = $this->occupancy();
        $first = $this->report($one, $user, ['title' => 'Lỗi 10% phòng_A!', 'description' => 'Đường ống rò']);
        $this->travel(1)->minutes();
        $this->report($two, $other, ['title' => 'Ổ điện hỏng']);
        $this->actingAs($this->owner, 'web');
        $this->getJson('/api/owner/maintenance-requests?occupancy_id='.$one->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/maintenance-requests?room_id='.$two->room_id)->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/maintenance-requests?rental_group_id='.$one->rental_group_id)->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/maintenance-requests?submitted_by='.$user->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/maintenance-requests?from=2026-03-05%2012:00:00&until=2026-03-05%2012:00:00')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/owner/maintenance-requests?from=2026-03-05%2012:01:00')->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/maintenance-requests?until=2026-03-05%2012:00:00')->assertJsonCount(1, 'data');
        foreach (['%', '_', '!', 'Đường ống'] as $text) {
            $this->getJson('/api/owner/maintenance-requests?q='.rawurlencode($text))->assertJsonCount(1, 'data');
        }
        $token = $this->getJson('/api/owner/maintenance-requests/'.$first->id)->json('data.version_token');
        $this->process($first->id, $token, ['status' => 'in_progress', 'resolution' => 'Hẹn sửa buổi chiều'])->assertOk();
        $this->getJson('/api/owner/maintenance-requests?status=in_progress')->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/maintenance-requests?q='.rawurlencode('buổi chiều'))->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/maintenance-requests?per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_invalid_filters_are_reported_for_both_roles(): void
    {
        [, $user] = $this->occupancy();
        foreach ([['user' => $this->owner, 'prefix' => 'owner'], ['user' => $user, 'prefix' => 'tenant']] as $case) {
            $this->actingAs($case['user'], 'web');
            $this->getJson('/api/'.$case['prefix'].'/maintenance-requests?status=wrong&per_page=101&room_id=0')->assertUnprocessable()
                ->assertJsonValidationErrors(['status', 'per_page', 'room_id']);
            $this->getJson('/api/'.$case['prefix'].'/maintenance-requests?from=2026-03-05%2013:00:00&until=2026-03-05%2012:00:00')->assertUnprocessable();
        }
    }

    public function test_representative_change_preserves_author_visibility_but_not_old_privileges(): void
    {
        [$occupancy, $oldUser] = $this->occupancy();
        [$nextTenant, $nextUser] = $this->member($occupancy);
        $old = $this->report($occupancy, $oldUser);
        $this->travel(1)->minutes();
        $this->actingAs($this->owner, 'web')->postJson('/api/owner/occupancies/'.$occupancy->id.'/representative', [
            'tenant_id' => $nextTenant->id, 'started_at' => now()->format('Y-m-d H:i:s'),
        ])->assertOk();
        $this->actingAs($oldUser, 'web');
        $this->send($occupancy)->assertNotFound();
        $this->getJson('/api/tenant/maintenance-requests/'.$old->id)->assertOk();
        $this->actingAs($nextUser, 'web');
        $this->getJson('/api/tenant/maintenance-requests/'.$old->id)->assertNotFound();
        $this->getJson('/api/tenant/maintenance-requests')->assertJsonCount(0, 'data');
        $this->send($occupancy)->assertCreated()->assertJsonPath('data.submitted_by', $nextUser->id);
        $this->assertSame(2, MaintenanceRequest::count());
    }

    public function test_transfer_and_checkout_leave_reports_with_original_room_and_owner_can_finish_them(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $report = $this->report($occupancy, $user);
        $newRoom = $this->room();
        $this->travel(1)->minutes();
        $this->actingAs($this->owner, 'web');
        $transfer = $this->postJson('/api/owner/occupancies/'.$occupancy->id.'/transfer', [
            'room_id' => $newRoom->id, 'transferred_at' => now()->format('Y-m-d H:i:s'),
        ])->assertCreated();
        $next = Occupancy::findOrFail($transfer->json('data.id'));
        $this->actingAs($user, 'web');
        $this->getJson('/api/tenant/maintenance-requests/eligible-occupancies')->assertJsonCount(1, 'data')->assertJsonPath('data.0.occupancy_id', $next->id);
        $this->send($occupancy)->assertNotFound();
        $this->getJson('/api/tenant/maintenance-requests/'.$report->id)->assertOk()->assertJsonPath('data.room.id', $occupancy->room_id);
        $this->send($next)->assertCreated();
        $this->travel(1)->minutes();
        $this->actingAs($this->owner, 'web')->postJson('/api/owner/occupancies/'.$next->id.'/checkout', ['ended_at' => now()->format('Y-m-d H:i:s')])->assertOk();
        $this->actingAs($user, 'web');
        $this->getJson('/api/tenant/maintenance-requests/eligible-occupancies')->assertJsonCount(0, 'data');
        $this->send($next)->assertNotFound();
        $this->getJson('/api/tenant/maintenance-requests')->assertJsonCount(2, 'data');
        $this->actingAs($this->owner, 'web');
        $token = $this->getJson('/api/owner/maintenance-requests/'.$report->id)->json('data.version_token');
        $this->process($report->id, $token, ['status' => 'resolved', 'resolution' => 'Đã sửa phòng cũ'])->assertOk();
        $this->actingAs($user, 'web')->getJson('/api/tenant/maintenance-requests/'.$report->id)->assertJsonPath('data.status', 'resolved');
    }

    public function test_report_submission_prevents_backdating_checkout_before_the_report(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $this->report($occupancy, $user);
        $this->actingAs($this->owner, 'web')->postJson('/api/owner/occupancies/'.$occupancy->id.'/checkout', ['ended_at' => '2026-03-05 11:59:59'])
            ->assertUnprocessable()->assertJsonValidationErrors('ended_at');
        $this->assertNull($occupancy->fresh()->ended_at);
    }

    public function test_reporting_does_not_automatically_change_room_operational_status(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $occupancy->room->update(['operational_status' => 'maintenance']);
        $report = $this->report($occupancy, $user);
        $this->actingAs($this->owner, 'web');
        $token = $this->getJson('/api/owner/maintenance-requests/'.$report->id)->json('data.version_token');
        $this->process($report->id, $token, ['status' => 'resolved', 'resolution' => 'Đã sửa lỗi nhỏ, còn hạng mục khác'])->assertOk();
        $this->assertSame('maintenance', $occupancy->room->fresh()->operational_status);
    }

    public function test_owner_cannot_impersonate_a_tenant_and_inactive_or_guest_access_is_denied(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $this->actingAs($this->owner, 'web');
        $this->send($occupancy)->assertForbidden();
        $this->getJson('/api/tenant/maintenance-requests')->assertForbidden();
        $this->getJson('/api/owner/maintenance-requests/999999')->assertNotFound();
        $this->actingAs($user, 'web');
        $user->update(['is_active' => false]);
        $this->send($occupancy)->assertUnauthorized();
        auth('web')->logout();
        $this->getJson('/api/tenant/maintenance-requests')->assertUnauthorized();
        $this->getJson('/api/owner/maintenance-requests')->assertUnauthorized();
    }

    public function test_original_reports_cannot_be_deleted_or_rewritten_by_tenants(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $report = $this->report($occupancy, $user);
        $this->patchJson('/api/tenant/maintenance-requests/'.$report->id, ['title' => 'Ghi đè'])->assertStatus(405);
        $this->deleteJson('/api/tenant/maintenance-requests/'.$report->id)->assertStatus(405);
        $this->actingAs($this->owner, 'web')->deleteJson('/api/owner/maintenance-requests/'.$report->id)->assertStatus(405);
        $this->assertSame(1, MaintenanceRequest::count());
    }

    public function test_csrf_protects_tenant_submission_and_owner_processing(): void
    {
        [$occupancy, $user] = $this->occupancy();
        $this->actingAs($user, 'web');
        $this->app->instance('env', 'local');
        $csrf = str_repeat('m', 40);
        $this->withSession(['_token' => $csrf]);
        $this->send($occupancy)->assertStatus(419);
        $created = $this->postJson('/api/tenant/occupancies/'.$occupancy->id.'/maintenance-requests', $this->payload(), ['X-CSRF-TOKEN' => $csrf])->assertCreated();
        $this->actingAs($this->owner, 'web');
        $token = $this->getJson('/api/owner/maintenance-requests/'.$created->json('data.id'))->json('data.version_token');
        $this->process($created->json('data.id'), $token, ['status' => 'in_progress'])->assertStatus(419);
        $this->patchJson('/api/owner/maintenance-requests/'.$created->json('data.id'), ['version_token' => $token, 'status' => 'in_progress'], ['X-CSRF-TOKEN' => $csrf])->assertOk();
    }

    private function payload(array $changes = []): array
    {
        return array_merge(['title' => 'Vòi nước rò rỉ', 'description' => 'Vòi nước trong nhà vệ sinh bị rò liên tục.'], $changes);
    }

    private function send(Occupancy $occupancy, array $changes = [])
    {
        return $this->postJson('/api/tenant/occupancies/'.$occupancy->id.'/maintenance-requests', $this->payload($changes));
    }

    private function report(Occupancy $occupancy, User $user, array $changes = []): MaintenanceRequest
    {
        $this->actingAs($user, 'web');
        $created = $this->send($occupancy, $changes)->assertCreated();

        return MaintenanceRequest::findOrFail($created->json('data.id'));
    }

    private function process(int $id, string $token, array $values)
    {
        return $this->patchJson('/api/owner/maintenance-requests/'.$id, array_merge(['version_token' => $token], $values));
    }

    private function room(): Room
    {
        return Room::create(['room_number' => 'M-'.(Room::count() + 1), 'capacity' => 4, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
    }

    private function member(Occupancy $occupancy): array
    {
        $tenant = Tenant::create(['full_name' => 'Người thuê thử', 'phone_number' => '0900000000']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);

        return [$tenant, $user, $stay];
    }

    private function occupancy(): array
    {
        $room = $this->room();
        $group = RentalGroup::create(['group_code' => (string) Str::uuid()]);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '3000000']);
        [, $user, $stay] = $this->member($occupancy);
        $representative = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);

        return [$occupancy, $user, $representative, $stay];
    }
}
