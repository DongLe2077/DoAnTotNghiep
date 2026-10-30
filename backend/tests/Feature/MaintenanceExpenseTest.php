<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\MaintenanceExpenseHistory;
use App\Models\MaintenanceExpenseLink;
use App\Models\MaintenanceRequest;
use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use App\Services\CashBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class MaintenanceExpenseTest extends TestCase
{
    use CreatesCashBookSchema;

    private User $owner;

    private User $tenant;

    private MaintenanceRequest $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
        $this->createCashBookSchema();
        $this->owner = User::factory()->create(['username' => 'expense_landlord']);
        $person = Tenant::create(['full_name' => 'Người thuê thử', 'phone_number' => '0900000000']);
        $this->tenant = User::factory()->create(['role' => 'tenant', 'tenant_id' => $person->id]);
        $room = Room::create(['room_number' => 'EXPENSE', 'capacity' => 2, 'default_monthly_rent' => '100', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => 'EXPENSE']);
        $stay = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '100']);
        $member = TenantStay::create(['occupancy_id' => $stay->id, 'tenant_id' => $person->id, 'moved_in_at' => '2026-01-01']);
        $representative = RepresentativeAssignment::create(['occupancy_id' => $stay->id, 'tenant_stay_id' => $member->id, 'started_at' => '2026-01-01']);
        $this->report = MaintenanceRequest::create(['occupancy_id' => $stay->id, 'submitted_by' => $this->tenant->id,
            'representative_assignment_id' => $representative->id, 'title' => 'Rò van nước', 'description' => 'Cần thay van', 'status' => 'submitted'])->refresh();
        $this->actingAs($this->owner, 'web');
    }

    private function expense(array $changes = []): CashTransaction
    {
        return app(CashBook::class)->create($changes + ['transaction_type' => 'expense', 'amount' => '350000.25',
            'occurred_at' => '2026-10-26 10:00:00', 'description' => 'Tiền thay van nước'], $this->owner->id);
    }

    private function payload(CashTransaction $cash, array $changes = []): array
    {
        return $changes + ['cash_transaction_id' => $cash->id, 'cash_version_token' => CashBook::version($cash->refresh()),
            'reason' => 'Chi phí thay van nước thực tế', 'idempotency_key' => (string) Str::uuid()];
    }

    private function url(?MaintenanceRequest $report = null): string
    {
        return '/api/owner/maintenance-requests/'.($report ?? $this->report)->id.'/expenses';
    }

    private function anotherReport(): MaintenanceRequest
    {
        $report = $this->report->replicate();
        $report->title = 'Sự cố thứ hai';
        $report->save();

        return $report->refresh();
    }

    public function test_linking_existing_expenses_never_duplicates_cash_and_totals_follow_the_same_rows(): void
    {
        $before = $this->report->getAttributes();
        $cash = $this->expense();
        $payload = $this->payload($cash);
        $linked = $this->postJson($this->url(), $payload)->assertCreated()->assertJsonPath('replayed', false)
            ->assertJsonPath('data.cash_transaction_id', $cash->id)->assertJsonPath('data.included_in_total', true);
        $this->postJson($this->url(), $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('history_id', $linked->json('history_id'));
        $second = $this->expense(['amount' => '99.75']);
        $this->postJson($this->url(), $this->payload($second))->assertCreated();
        $this->getJson($this->url())->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('summary.total_amount', '350100.00')
            ->assertJsonPath('summary.active_count', 2)->assertJsonPath('summary.deleted_count', 0);
        $this->getJson('/api/owner/cash-flow/summary')->assertOk()->assertJsonPath('data.expense_total', '350100.00')
            ->assertJsonPath('data.expense_count', 2);
        $this->assertSame(2, CashTransaction::count());
        $this->assertSame(2, MaintenanceExpenseLink::count());
        $this->assertSame(2, MaintenanceExpenseHistory::count());
        $this->assertEquals($before, $this->report->fresh()->getAttributes());
    }

    public function test_invalid_or_stale_cash_and_conflicting_retries_do_not_make_links(): void
    {
        $income = $this->expense(['transaction_type' => 'other_income']);
        $this->postJson($this->url(), $this->payload($income))->assertConflict();
        $cash = $this->expense();
        $stale = $this->payload($cash);
        app(CashBook::class)->update($cash, ['amount' => '20.50', 'version_token' => CashBook::version($cash)], $this->owner->id);
        $this->postJson($this->url(), $stale)->assertConflict();
        $cash->refresh();
        $payload = $this->payload($cash);
        $this->postJson($this->url(), $payload)->assertCreated();
        $this->postJson($this->url(), ['reason' => 'Nội dung bị thay đổi'] + $payload)->assertConflict();
        $this->postJson($this->url(), $this->payload($cash))->assertConflict();
        $other = $this->expense();
        $this->postJson($this->url(), ['cash_transaction_id' => $other->id, 'cash_version_token' => CashBook::version($other)] + $payload)->assertConflict();
        $deleted = $this->expense();
        app(CashBook::class)->delete($deleted, CashBook::version($deleted), $this->owner->id, 'Ghi nhầm khoản');
        $this->postJson($this->url(), $this->payload($deleted))->assertConflict();
        $this->assertSame(1, MaintenanceExpenseLink::count());
        $this->assertSame(1, MaintenanceExpenseHistory::count());
    }

    public function test_unlink_relink_requires_latest_version_and_replay_never_reactivates_old_link(): void
    {
        $cash = $this->expense();
        $original = $this->payload($cash);
        $first = $this->postJson($this->url(), $original)->assertCreated()->json('data');
        $other = $this->anotherReport();
        $this->postJson($this->url($other), $this->payload($cash))->assertConflict();
        $unlink = ['version_token' => $first['version_token'], 'reason' => 'Gắn nhầm yêu cầu', 'idempotency_key' => (string) Str::uuid()];
        $unlinked = $this->postJson($this->url().'/'.$cash->id.'/unlink', $unlink)->assertOk()->assertJsonPath('data.is_active', false)->json('data');
        $this->postJson($this->url().'/'.$cash->id.'/unlink', $unlink)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson($this->url($other), $this->payload($cash))->assertConflict();
        $this->postJson($this->url($other), $this->payload($cash, ['version_token' => $first['version_token']]))->assertConflict();
        $this->postJson($this->url($other), $this->payload($cash, ['version_token' => $unlinked['version_token']]))->assertCreated();
        $this->postJson($this->url(), $original)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.maintenance_request_id', $other->id);
        $this->postJson($this->url().'/'.$cash->id.'/unlink', $unlink)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.is_active', true);
        $this->getJson($this->url())->assertJsonCount(0, 'data')->assertJsonPath('summary.total_amount', '0.00');
        $this->getJson($this->url($other))->assertJsonCount(1, 'data');
        $this->assertSame(1, CashTransaction::count());
        $this->assertSame(1, MaintenanceExpenseLink::count());
        $this->assertSame(3, MaintenanceExpenseHistory::count());
        $this->getJson($this->url().'/history')->assertJsonCount(2, 'data')->assertJsonPath('data.0.action', 'unlinked');
    }

    public function test_cash_edits_delete_restore_and_replay_preserve_one_financial_source(): void
    {
        $cash = $this->expense();
        $payload = $this->payload($cash);
        $this->postJson($this->url(), $payload)->assertCreated();
        $updated = app(CashBook::class)->update($cash, ['amount' => '123.45', 'version_token' => CashBook::version($cash)], $this->owner->id);
        $this->getJson($this->url())->assertJsonPath('summary.total_amount', '123.45');
        app(CashBook::class)->delete($updated, CashBook::version($updated), $this->owner->id, 'Xóa nhầm');
        $this->getJson($this->url())->assertJsonCount(1, 'data')->assertJsonPath('summary.total_amount', '0.00')
            ->assertJsonPath('summary.deleted_count', 1)->assertJsonPath('data.0.cash_transaction.is_deleted', true)->assertJsonPath('data.0.included_in_total', false);
        $this->postJson($this->url(), $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.cash_transaction.is_deleted', true);
        $history = $this->getJson('/api/owner/cash-transactions/'.$cash->id.'/history')->assertOk()->json();
        $this->postJson('/api/owner/cash-transactions/'.$cash->id.'/revert', ['history_id' => $history['data'][0]['id'],
            'version_token' => $history['current']['version_token'], 'reason' => 'Khôi phục khoản chi'])->assertOk();
        $this->getJson($this->url())->assertJsonPath('summary.total_amount', '123.45')->assertJsonPath('summary.active_count', 1);
        $this->assertSame(1, MaintenanceExpenseHistory::count());
        $this->assertSame(1, CashTransaction::withTrashed()->count());
    }

    public function test_active_links_block_income_conversion_in_updates_and_reverts(): void
    {
        $cash = $this->expense(['transaction_type' => 'other_income']);
        $current = $this->patchJson('/api/owner/cash-transactions/'.$cash->id,
            ['transaction_type' => 'expense', 'version_token' => CashBook::version($cash), 'reason' => 'Đổi loại đúng'])->assertOk()->json('data');
        $latest = $this->getJson('/api/owner/cash-transactions/'.$cash->id.'/history')->json('data.0');
        $linked = $this->postJson($this->url(), $this->payload($cash))->assertCreated()->json('data');
        $this->patchJson('/api/owner/cash-transactions/'.$cash->id, ['transaction_type' => 'other_income',
            'version_token' => $current['version_token'], 'reason' => 'Đổi thành thu'])->assertConflict();
        $this->postJson('/api/owner/cash-transactions/'.$cash->id.'/revert', ['history_id' => $latest['id'],
            'version_token' => $current['version_token'], 'reason' => 'Đảo về thu'])->assertConflict();
        $this->postJson($this->url().'/'.$cash->id.'/unlink', ['version_token' => $linked['version_token'],
            'reason' => 'Gỡ để sửa loại', 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->patchJson('/api/owner/cash-transactions/'.$cash->id, ['transaction_type' => 'other_income',
            'version_token' => $current['version_token'], 'reason' => 'Đã gỡ liên kết'])->assertOk();
        $this->getJson($this->url())->assertJsonCount(0, 'data');
    }

    public function test_history_failure_rolls_back_link_state_and_dedup_record(): void
    {
        $cash = $this->expense();
        $payload = $this->payload($cash);
        DB::statement("CREATE TRIGGER maintenance_expense_failure BEFORE INSERT ON maintenance_expense_histories BEGIN SELECT RAISE(ABORT, 'history unavailable'); END");
        $this->postJson($this->url(), $payload)->assertServerError();
        $this->assertSame(0, MaintenanceExpenseLink::count());
        $this->assertSame(0, MaintenanceExpenseHistory::count());
        $this->assertSame(1, CashTransaction::count());
        DB::statement('DROP TRIGGER maintenance_expense_failure');
        $linked = $this->postJson($this->url(), $payload)->assertCreated()->json('data');
        DB::statement("CREATE TRIGGER maintenance_expense_failure BEFORE INSERT ON maintenance_expense_histories BEGIN SELECT RAISE(ABORT, 'history unavailable'); END");
        $this->postJson($this->url().'/'.$cash->id.'/unlink', ['version_token' => $linked['version_token'],
            'reason' => 'Không lưu một phần', 'idempotency_key' => (string) Str::uuid()])->assertServerError();
        $this->assertTrue(MaintenanceExpenseLink::first()->is_active);
        $this->assertSame(1, MaintenanceExpenseLink::first()->revision);
        $this->assertSame(1, MaintenanceExpenseHistory::count());
    }

    public function test_only_owner_can_read_or_change_links_and_fields_are_validated(): void
    {
        $cash = $this->expense();
        $payload = $this->payload($cash);
        $this->postJson($this->url(), ['reason' => ' '] + $payload)->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->url(), ['performed_by' => $this->tenant->id] + $payload)->assertUnprocessable()->assertJsonValidationErrors('performed_by');
        $this->postJson($this->url(), ['amount' => '1'] + $payload)->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->getJson($this->url().'?per_page=51')->assertUnprocessable();
        $this->postJson($this->url(), ['cash_transaction_id' => 999999] + $payload)->assertNotFound();
        $this->postJson('/api/owner/maintenance-requests/999999/expenses', $payload)->assertNotFound();
        $this->postJson($this->url(), $payload)->assertCreated();
        $this->actingAs($this->tenant, 'web');
        $this->getJson($this->url())->assertForbidden();
        $this->getJson($this->url().'/history')->assertForbidden();
        $this->getJson('/api/owner/cash-transactions/'.$cash->id.'/maintenance-link')->assertForbidden();
        $this->postJson($this->url(), $payload)->assertForbidden();
        $this->postJson($this->url().'/'.$cash->id.'/unlink', [])->assertForbidden();
        $this->getJson('/api/tenant/maintenance-requests/'.$this->report->id)->assertOk()
            ->assertJsonMissingPath('data.expenses')->assertJsonMissingPath('data.expense_total')->assertJsonMissingPath('data.cash_transactions');
    }

    public function test_pagination_actor_snapshots_and_context_remain_truthful(): void
    {
        $cash = $this->expense();
        $first = $this->postJson($this->url(), $this->payload($cash))->assertCreated()->json('data');
        $this->owner->update(['username' => 'renamed_owner']);
        $this->postJson($this->url().'/'.$cash->id.'/unlink', ['version_token' => $first['version_token'],
            'reason' => 'Bỏ liên kết mẫu', 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->getJson($this->url().'/history?per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.actor.username', 'renamed_owner')->assertJsonPath('data.0.before.is_active', true)
            ->assertJsonPath('data.0.after.is_active', false);
        $this->getJson($this->url().'/history?per_page=1&page=2')->assertJsonPath('data.0.actor.username', 'expense_landlord');
        $this->getJson('/api/owner/cash-transactions/'.$cash->id.'/maintenance-link')->assertOk()->assertJsonPath('data.is_active', false);
        $new = $this->expense();
        $this->getJson('/api/owner/cash-transactions/'.$new->id.'/maintenance-link')->assertOk()->assertJsonPath('data', null);
    }

    public function test_migration_rollback_cannot_discard_link_audits(): void
    {
        $cash = $this->expense();
        $this->postJson($this->url(), $this->payload($cash))->assertCreated();
        $migration = require database_path('migrations/2026_10_27_000001_create_maintenance_expense_links.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
