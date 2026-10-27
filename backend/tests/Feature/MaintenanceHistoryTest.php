<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestHistory;
use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class MaintenanceHistoryTest extends TestCase
{
    use CreatesBillingSchema;

    private User $owner;

    private User $author;

    private Occupancy $stay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBillingSchema();
        $this->owner = User::factory()->create(['username' => 'landlord_history']);
        $tenant = Tenant::create(['full_name' => 'Người gửi thử', 'phone_number' => '0900000000']);
        $this->author = User::factory()->create(['role' => 'tenant', 'tenant_id' => $tenant->id]);
        $room = Room::create(['room_number' => 'HISTORY', 'capacity' => 2, 'default_monthly_rent' => '100', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => 'HISTORY']);
        $this->stay = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '100']);
        $member = TenantStay::create(['occupancy_id' => $this->stay->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        RepresentativeAssignment::create(['occupancy_id' => $this->stay->id, 'tenant_stay_id' => $member->id, 'started_at' => '2026-01-01']);
    }

    private function submit(): MaintenanceRequest
    {
        $response = $this->actingAs($this->author, 'web')->postJson('/api/tenant/occupancies/'.$this->stay->id.'/maintenance-requests', ['title' => 'Rò nước', 'description' => 'Cần kiểm tra van nước'])->assertCreated();

        return MaintenanceRequest::findOrFail($response->json('data.id'));
    }

    private function updateReport(MaintenanceRequest $report, array $values)
    {
        $token = $this->actingAs($this->owner, 'web')->getJson('/api/owner/maintenance-requests/'.$report->id)->assertOk()->json('data.version_token');

        return $this->patchJson('/api/owner/maintenance-requests/'.$report->id, ['version_token' => $token] + $values);
    }

    public function test_submission_and_duplicate_replay_keep_one_real_initial_event(): void
    {
        $report = $this->submit();
        $this->postJson('/api/tenant/occupancies/'.$this->stay->id.'/maintenance-requests', ['title' => 'Rò nước', 'description' => 'Cần kiểm tra van nước'])->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(1, MaintenanceRequestHistory::count());
        $this->getJson('/api/tenant/maintenance-requests/'.$report->id.'/history')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'submitted')->assertJsonPath('data.0.before.status', null)
            ->assertJsonPath('data.0.after.status', 'submitted')->assertJsonPath('data.0.actor.label', 'Bạn')
            ->assertJsonMissingPath('data.0.actor.username')->assertJsonMissingPath('data.0.actor.id');
    }

    public function test_status_notes_clearing_and_completion_record_before_after_and_actor_snapshots(): void
    {
        $report = $this->submit();
        $this->updateReport($report, ['status' => 'in_progress', 'resolution' => 'Hẹn thợ buổi sáng'])->assertOk();
        $this->owner->update(['username' => 'renamed_landlord']);
        $this->updateReport($report, ['status' => 'in_progress', 'resolution' => 'Chờ linh kiện'])->assertOk();
        $this->updateReport($report, ['status' => 'in_progress', 'resolution' => null])->assertOk();
        $this->updateReport($report, ['status' => 'resolved', 'resolution' => 'Đã thay van'])->assertOk();
        $history = $this->getJson('/api/owner/maintenance-requests/'.$report->id.'/history')->assertOk()->assertJsonCount(5, 'data');
        $history->assertJsonPath('data.0.after.status', 'resolved')->assertJsonPath('data.0.before.status', 'in_progress')
            ->assertJsonPath('data.0.after.resolution', 'Đã thay van')->assertJsonPath('data.1.before.resolution', 'Chờ linh kiện')
            ->assertJsonPath('data.1.after.resolution', null)->assertJsonPath('data.3.actor.username', 'landlord_history')
            ->assertJsonPath('data.0.actor.username', 'renamed_landlord');
        $this->actingAs($this->author, 'web')->getJson('/api/tenant/maintenance-requests/'.$report->id.'/history')->assertOk()
            ->assertJsonPath('data.0.actor.label', 'Chủ trọ')->assertJsonMissingPath('data.0.actor.username')->assertJsonMissingPath('data.0.performed_by');
        $this->updateReport($report, ['status' => 'resolved', 'resolution' => 'Ghi đè'])->assertConflict();
        $this->assertSame(5, MaintenanceRequestHistory::count());
    }

    public function test_noop_and_stale_version_never_create_history_or_notifications(): void
    {
        $report = $this->submit();
        $first = $this->updateReport($report, ['status' => 'in_progress', 'resolution' => 'Đang kiểm tra'])->assertOk();
        $count = AppNotification::count();
        $token = $first->json('data.version_token');
        $this->patchJson('/api/owner/maintenance-requests/'.$report->id, ['status' => 'in_progress', 'resolution' => 'Đang kiểm tra', 'version_token' => $token])->assertOk();
        $this->assertSame(2, MaintenanceRequestHistory::count());
        $this->assertSame($count, AppNotification::count());
        $this->updateReport($report, ['status' => 'in_progress', 'resolution' => 'Đã kiểm tra'])->assertOk();
        $this->patchJson('/api/owner/maintenance-requests/'.$report->id, ['status' => 'resolved', 'resolution' => 'Sai phiên bản', 'version_token' => $token])->assertConflict();
        $this->assertSame(3, MaintenanceRequestHistory::count());
    }

    public function test_history_scope_survives_checkout_but_rejects_other_tenants_and_forgery(): void
    {
        $report = $this->submit();
        $other = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($other, 'web')->getJson('/api/tenant/maintenance-requests/'.$report->id.'/history')->assertNotFound();
        $this->getJson('/api/owner/maintenance-requests/'.$report->id.'/history')->assertForbidden();
        TenantStay::where('occupancy_id', $this->stay->id)->update(['moved_out_at' => now()]);
        $this->stay->update(['ended_at' => now()]);
        $this->actingAs($this->author, 'web')->getJson('/api/tenant/maintenance-requests/'.$report->id.'/history')->assertOk();
        $this->postJson('/api/tenant/maintenance-requests/'.$report->id.'/history', ['action' => 'updated'])->assertMethodNotAllowed();
        $this->actingAs($this->owner, 'web');
        $token = $this->getJson('/api/owner/maintenance-requests/'.$report->id)->json('data.version_token');
        foreach (['performed_by', 'actor_username', 'actor_role', 'created_at', 'histories'] as $field) {
            $this->patchJson('/api/owner/maintenance-requests/'.$report->id, ['status' => 'in_progress', 'version_token' => $token, $field => 'forged'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(1, MaintenanceRequestHistory::count());
    }

    public function test_history_failure_rolls_back_processing_revision_and_notifications(): void
    {
        $report = $this->submit();
        $before = $report->getAttributes();
        $count = AppNotification::count();
        DB::statement("CREATE TRIGGER history_failure BEFORE INSERT ON maintenance_request_histories BEGIN SELECT RAISE(ABORT, 'history unavailable'); END");
        $this->updateReport($report, ['status' => 'in_progress', 'resolution' => 'Không được lưu một phần'])->assertStatus(500);
        $this->assertEquals($before, $report->fresh()->getAttributes());
        $this->assertSame(1, MaintenanceRequestHistory::count());
        $this->assertSame($count, AppNotification::count());
    }

    public function test_history_failure_rolls_back_initial_submission(): void
    {
        DB::statement("CREATE TRIGGER history_failure BEFORE INSERT ON maintenance_request_histories BEGIN SELECT RAISE(ABORT, 'history unavailable'); END");
        $this->actingAs($this->author, 'web')->postJson('/api/tenant/occupancies/'.$this->stay->id.'/maintenance-requests', ['title' => 'Không lưu', 'description' => 'Kiểm tra giao dịch'])->assertStatus(500);
        $this->assertSame(0, MaintenanceRequest::count());
        $this->assertSame(0, MaintenanceRequestHistory::count());
        $this->assertSame(0, AppNotification::count());
    }

    public function test_legacy_migration_preserves_rows_and_marks_only_known_current_state(): void
    {
        $report = $this->submit();
        $this->updateReport($report, ['status' => 'resolved', 'resolution' => 'Ghi chú cũ còn lưu'])->assertOk();
        $before = $report->fresh()->getAttributes();
        Schema::drop('maintenance_request_histories');
        $migration = require database_path('migrations/2026_10_25_000001_create_maintenance_request_histories.php');
        $migration->up();
        $this->assertEquals($before, $report->fresh()->getAttributes());
        $this->assertSame(1, MaintenanceRequestHistory::count());
        $this->getJson('/api/owner/maintenance-requests/'.$report->id.'/history')->assertOk()
            ->assertJsonPath('data.0.action', 'baseline')->assertJsonPath('data.0.actor', null)
            ->assertJsonPath('data.0.before.status', null)->assertJsonPath('data.0.after.status', 'resolved')
            ->assertJsonPath('data.0.after.resolution', 'Ghi chú cũ còn lưu');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_pagination_is_stable_and_query_limits_are_validated(): void
    {
        $report = $this->submit();
        foreach (range(1, 12) as $number) {
            $this->updateReport($report, ['status' => 'in_progress', 'resolution' => 'Tiến độ '.$number])->assertOk();
        }
        $this->getJson('/api/owner/maintenance-requests/'.$report->id.'/history?per_page=5')->assertOk()->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 13)->assertJsonPath('data.0.after.resolution', 'Tiến độ 12');
        $this->getJson('/api/owner/maintenance-requests/'.$report->id.'/history?per_page=5&page=3')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.2.action', 'submitted');
        $this->getJson('/api/owner/maintenance-requests/'.$report->id.'/history?per_page=51&page=0')->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'page']);
    }
}
