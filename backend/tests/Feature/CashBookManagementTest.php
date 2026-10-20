<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\Payment;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class CashBookManagementTest extends TestCase
{
    use CreatesCashBookSchema;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 5)->setTime(12, 0));
        $this->createCashBookSchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
    }

    public function test_owner_can_create_read_correct_and_delete_other_income_and_expenses(): void
    {
        $created = $this->createEntry()->assertCreated()->assertJsonPath('data.transaction_type', 'expense')
            ->assertJsonPath('data.amount', '50000.00')->assertJsonPath('data.recorded_by', $this->owner->id);
        $id = $created->json('data.id');
        $token = $created->json('data.version_token');
        $this->getJson('/api/owner/cash-transactions/'.$id)->assertOk()->assertJsonPath('data.version_token', $token);
        $updated = $this->patchJson('/api/owner/cash-transactions/'.$id, [
            'version_token' => $token, 'amount' => '60000.25', 'description' => 'Chi sửa khóa phòng',
        ])->assertOk()->assertJsonPath('data.amount', '60000.25')->assertJsonPath('data.description', 'Chi sửa khóa phòng');
        $this->assertNotSame($token, $updated->json('data.version_token'));
        $this->assertSame($created->json('data.recorded_at'), $updated->json('data.recorded_at'));
        $this->createEntry(['transaction_type' => 'other_income', 'amount' => '20000'])->assertCreated();
        $this->getJson('/api/owner/cash-transactions')->assertOk()->assertJsonCount(2, 'data');
        $this->deleteJson('/api/owner/cash-transactions/'.$id, ['version_token' => $updated->json('data.version_token')])->assertNoContent();
        $this->getJson('/api/owner/cash-transactions/'.$id)->assertNotFound();
        $this->assertSame(1, CashTransaction::count());
    }

    public function test_stale_or_missing_version_cannot_overwrite_or_delete_newer_data(): void
    {
        $first = $this->createEntry()->assertCreated();
        $id = $first->json('data.id');
        $token = $first->json('data.version_token');
        $this->putJson('/api/owner/cash-transactions/'.$id, ['version_token' => $token, 'amount' => '100'])->assertOk();
        $this->patchJson('/api/owner/cash-transactions/'.$id, ['version_token' => $token, 'amount' => '200'])->assertConflict();
        $this->deleteJson('/api/owner/cash-transactions/'.$id, ['version_token' => $token])->assertConflict();
        $this->patchJson('/api/owner/cash-transactions/'.$id, ['amount' => '200'])->assertUnprocessable()->assertJsonValidationErrors('version_token');
        $this->deleteJson('/api/owner/cash-transactions/'.$id, [])->assertUnprocessable();
        $this->assertSame('100.00', CashTransaction::findOrFail($id)->amount);
    }

    public function test_tokens_cannot_be_reused_for_another_entry(): void
    {
        $first = $this->createEntry()->assertCreated();
        $second = $this->createEntry()->assertCreated();
        $this->patchJson('/api/owner/cash-transactions/'.$second->json('data.id'), [
            'version_token' => $first->json('data.version_token'), 'amount' => '1',
        ])->assertConflict();
        $this->assertSame(2, CashTransaction::count());
    }

    public function test_correction_preserves_original_recorder_and_does_not_touch_invoice_receipts(): void
    {
        $entry = $this->createEntry()->assertCreated();
        $receipt = $this->receipt('100');
        $before = $receipt->fresh()->getAttributes();
        $otherOwner = User::factory()->create();
        $this->actingAs($otherOwner, 'web');
        $this->patchJson('/api/owner/cash-transactions/'.$entry->json('data.id'), [
            'version_token' => $entry->json('data.version_token'), 'transaction_type' => 'other_income',
        ])->assertOk()->assertJsonPath('data.recorded_by', $this->owner->id);
        $this->assertSame($before, $receipt->fresh()->getAttributes());
        $this->assertSame(1, Payment::count());
    }

    #[DataProvider('invalidEntries')]
    public function test_invalid_cash_entries_are_rejected(array $changes, string $field): void
    {
        $this->createEntry($changes)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, CashTransaction::count());
    }

    public static function invalidEntries(): array
    {
        return [
            'zero' => [['amount' => '0'], 'amount'],
            'negative' => [['amount' => '-1'], 'amount'],
            'fraction precision' => [['amount' => '0.001'], 'amount'],
            'overflow' => [['amount' => '10000000000000000'], 'amount'],
            'thousands separator' => [['amount' => '1,000'], 'amount'],
            'scientific string' => [['amount' => '1e3'], 'amount'],
            'array amount' => [['amount' => [100]], 'amount'],
            'unknown category' => [['transaction_type' => 'income'], 'transaction_type'],
            'invoice category' => [['transaction_type' => 'invoice_payment'], 'transaction_type'],
            'empty description' => [['description' => '  '], 'description'],
            'long description' => [['description' => str_repeat('a', 256)], 'description'],
            'future' => [['occurred_at' => '2026-03-06 00:00:00'], 'occurred_at'],
            'invalid date' => [['occurred_at' => '2026-02-30 00:00:00'], 'occurred_at'],
            'date only' => [['occurred_at' => '2026-03-01'], 'occurred_at'],
            'old date' => [['occurred_at' => '0999-12-31 23:59:59'], 'occurred_at'],
            'recorder spoof' => [['recorded_by' => 999], 'recorded_by'],
            'timestamp spoof' => [['recorded_at' => '2026-01-01'], 'recorded_at'],
            'invoice spoof' => [['invoice_id' => 1], 'invoice_id'],
            'payment spoof' => [['payment_id' => 1], 'payment_id'],
        ];
    }

    public function test_required_create_fields_and_invalid_edits_are_rejected(): void
    {
        $this->postJson('/api/owner/cash-transactions', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_type', 'amount', 'occurred_at', 'description']);
        $entry = $this->createEntry()->assertCreated();
        $this->patchJson('/api/owner/cash-transactions/'.$entry->json('data.id'), [
            'version_token' => $entry->json('data.version_token'), 'amount' => '0', 'description' => null,
        ])->assertUnprocessable();
        $this->assertSame('50000.00', CashTransaction::first()->amount);
    }

    public function test_cash_filters_dates_pagination_and_literal_search(): void
    {
        $this->createEntry(['description' => 'Chi phí 10% phòng_A!', 'occurred_at' => '2026-02-28 23:59:59'])->assertCreated();
        $this->createEntry(['transaction_type' => 'other_income', 'description' => 'Thu khác', 'occurred_at' => '2026-03-01 00:00:00'])->assertCreated();
        $this->getJson('/api/owner/cash-transactions?month=2026-02')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/cash-transactions?from=2026-03-01%2000:00:00')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/cash-transactions?until=2026-02-28%2023:59:59')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/cash-transactions?transaction_type=expense')->assertOk()->assertJsonCount(1, 'data');
        foreach (['%', '_', '!'] as $literal) {
            $this->getJson('/api/owner/cash-transactions?q='.rawurlencode($literal))->assertOk()->assertJsonCount(1, 'data');
        }
        $this->getJson('/api/owner/cash-transactions?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_summary_uses_only_money_actually_received_not_invoice_face_value(): void
    {
        $invoice = $this->invoice();
        $this->receipt('1000000', '2026-03-01 10:00:00', $invoice);
        $this->receipt('500000', '2026-03-02 10:00:00', $invoice);
        $this->createEntry(['transaction_type' => 'other_income', 'amount' => '200000', 'occurred_at' => '2026-03-01 11:00:00'])->assertCreated();
        $this->createEntry(['amount' => '300000', 'occurred_at' => '2026-03-02 11:00:00'])->assertCreated();
        $this->getJson('/api/owner/cash-flow/summary')->assertOk()
            ->assertJsonPath('data.invoice_payment_total', '1500000.00')->assertJsonPath('data.other_income_total', '200000.00')
            ->assertJsonPath('data.expense_total', '300000.00')->assertJsonPath('data.total_income', '1700000.00')
            ->assertJsonPath('data.net_cash_flow', '1400000.00')->assertJsonPath('data.entry_count', 4)
            ->assertJsonPath('data.invoice_payment_count', 2)->assertJsonCount(1, 'data.periods');
        $this->assertSame(2, CashTransaction::count());
        $this->assertSame(2, Payment::count());
    }

    public function test_unpaid_invoices_do_not_appear_as_cash_income(): void
    {
        $this->invoice();
        $this->getJson('/api/owner/cash-flow/summary')->assertOk()->assertJsonPath('data.total_income', '0.00')
            ->assertJsonPath('data.entry_count', 0)->assertJsonCount(0, 'data.periods');
        $this->getJson('/api/owner/cash-flow')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_multiple_invoice_lines_do_not_multiply_payment_income(): void
    {
        $invoice = $this->invoice();
        $service = Service::create(['service_code' => 'S1', 'name' => 'Internet', 'unit' => 'phòng', 'billing_method' => 'per_room', 'default_unit_price' => '100000', 'is_active' => true]);
        $period = OccupancyService::create(['occupancy_id' => $invoice->occupancy_id, 'service_id' => $service->id, 'effective_from' => '2026-01-01', 'unit_price' => '100000', 'unit' => 'phòng', 'billing_method' => 'per_room']);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $invoice->occupancy_id, 'item_type' => 'service',
            'description' => 'Internet', 'unit' => 'phòng', 'quantity' => '1', 'unit_price' => '100000',
            'occupancy_service_id' => $period->id, 'charge_period_start' => '2026-01-01', 'charge_period_end' => '2026-02-01']);
        $this->receipt('100', '2026-03-01 10:00:00', $invoice);
        $this->getJson('/api/owner/cash-flow/summary')->assertJsonPath('data.invoice_payment_total', '100.00')->assertJsonPath('data.entry_count', 1);
        $this->getJson('/api/owner/cash-flow')->assertJsonCount(1, 'data');
    }

    public function test_ledger_combines_distinct_ids_and_marks_invoice_receipts_read_only(): void
    {
        $receipt = $this->receipt('100');
        $entry = $this->createEntry()->assertCreated();
        $ledger = $this->getJson('/api/owner/cash-flow')->assertOk()->assertJsonCount(2, 'data');
        $rows = collect($ledger->json('data'));
        $cash = $rows->firstWhere('source', 'cash_transaction');
        $payment = $rows->firstWhere('source', 'payment');
        $this->assertSame('payment:'.$receipt->id, $payment['id']);
        $this->assertSame('cash_transaction:'.$entry->json('data.id'), $cash['id']);
        $this->assertTrue($cash['can_edit']);
        $this->assertFalse($payment['can_edit']);
        $this->assertFalse($payment['can_delete']);
        $this->assertSame('-50000.00', $cash['signed_amount']);
        $this->assertSame('100.00', $payment['signed_amount']);
        $this->assertArrayNotHasKey('idempotency_key', $payment);
        $this->getJson('/api/owner/cash-flow?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
    }

    public function test_summary_exact_cents_negative_net_and_day_grouping(): void
    {
        $this->receipt('0.10');
        $this->createEntry(['transaction_type' => 'other_income', 'amount' => '0.20'])->assertCreated();
        $this->createEntry(['amount' => '0.31', 'occurred_at' => '2026-03-02 00:00:00'])->assertCreated();
        $this->getJson('/api/owner/cash-flow/summary?group_by=day')->assertOk()->assertJsonPath('data.total_income', '0.30')
            ->assertJsonPath('data.expense_total', '0.31')->assertJsonPath('data.net_cash_flow', '-0.01')
            ->assertJsonCount(2, 'data.periods')->assertJsonPath('data.periods.0.period', '2026-03-01')
            ->assertJsonPath('data.periods.0.net_cash_flow', '0.30')->assertJsonPath('data.periods.1.net_cash_flow', '-0.31');
    }

    public function test_range_boundaries_and_month_groups_use_occurrence_date_not_billing_month(): void
    {
        $this->receipt('100', '2026-02-28 23:59:59');
        $this->receipt('200', '2026-03-01 00:00:00');
        $this->createEntry(['amount' => '50', 'occurred_at' => '2026-03-01 00:00:00'])->assertCreated();
        $this->getJson('/api/owner/cash-flow/summary?month=2026-02')->assertJsonPath('data.invoice_payment_total', '100.00');
        $this->getJson('/api/owner/cash-flow/summary?from=2026-02-28%2023:59:59&until=2026-03-01%2000:00:00')
            ->assertOk()->assertJsonPath('data.total_income', '300.00')->assertJsonCount(2, 'data.periods')
            ->assertJsonPath('data.periods.0.period', '2026-02')->assertJsonPath('data.periods.1.period', '2026-03');
        $this->getJson('/api/owner/cash-flow/summary?from=2026-03-01%2000:00:00&until=2026-03-01%2000:00:00')
            ->assertJsonPath('data.invoice_payment_total', '200.00')->assertJsonPath('data.expense_total', '50.00');
        $this->getJson('/api/owner/cash-flow?month=2026-02')->assertJsonCount(1, 'data');
    }

    public function test_ledger_and_summary_filters_have_the_same_scope(): void
    {
        $receipt = $this->receipt('100');
        $this->createEntry(['transaction_type' => 'other_income', 'amount' => '20', 'description' => 'Thu thanh lý'])->assertCreated();
        $this->createEntry(['amount' => '5'])->assertCreated();
        $this->getJson('/api/owner/cash-flow?entry_type=expense')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/cash-flow/summary?entry_type=expense')->assertJsonPath('data.total_income', '0.00')->assertJsonPath('data.net_cash_flow', '-5.00');
        $this->getJson('/api/owner/cash-flow?q='.rawurlencode($receipt->invoice->invoice_number))->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/cash-flow/summary?q='.rawurlencode('thanh lý'))->assertJsonPath('data.other_income_total', '20.00')->assertJsonPath('data.entry_count', 1);
    }

    public function test_edit_and_delete_are_reflected_in_summary_without_changing_payments(): void
    {
        $this->receipt('100');
        $entry = $this->createEntry(['amount' => '30'])->assertCreated();
        $updated = $this->patchJson('/api/owner/cash-transactions/'.$entry->json('data.id'), [
            'version_token' => $entry->json('data.version_token'), 'transaction_type' => 'other_income', 'amount' => '40',
        ])->assertOk();
        $this->getJson('/api/owner/cash-flow/summary')->assertJsonPath('data.total_income', '140.00')->assertJsonPath('data.expense_total', '0.00');
        $this->deleteJson('/api/owner/cash-transactions/'.$entry->json('data.id'), ['version_token' => $updated->json('data.version_token')])->assertNoContent();
        $this->getJson('/api/owner/cash-flow/summary')->assertJsonPath('data.total_income', '100.00')->assertJsonPath('data.entry_count', 1);
        $this->assertSame(1, Payment::count());
    }

    public function test_invalid_filter_and_mixed_month_range_are_rejected(): void
    {
        foreach (['month=2026-13', 'month=0000-01', 'month=2026-03&from=2026-03-01%2000:00:00',
            'from=2026-03-02%2000:00:00&until=2026-03-01%2000:00:00', 'per_page=101', 'group_by=year', 'entry_type=wrong'] as $query) {
            $this->getJson('/api/owner/cash-flow/summary?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/owner/cash-transactions?transaction_type=invoice_payment')->assertUnprocessable();
        $this->getJson('/api/owner/cash-flow/summary?month=9999-12')->assertOk()->assertJsonPath('data.entry_count', 0);
    }

    public function test_roles_guests_and_inactive_accounts_cannot_access_the_cash_book(): void
    {
        $this->getJson('/api/owner/cash-transactions/999999')->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        foreach (['/api/owner/cash-transactions', '/api/owner/cash-transactions/999999', '/api/owner/cash-flow', '/api/owner/cash-flow/summary'] as $path) {
            $this->getJson($path)->assertForbidden();
        }
        $this->createEntry()->assertForbidden();
        $this->patchJson('/api/owner/cash-transactions/999999', [])->assertForbidden();
        $this->deleteJson('/api/owner/cash-transactions/999999', [])->assertForbidden();
        $this->actingAs($this->owner, 'web');
        $this->owner->update(['is_active' => false]);
        $this->getJson('/api/owner/cash-flow/summary')->assertUnauthorized();
        auth('web')->logout();
        $this->createEntry()->assertUnauthorized();
        $this->assertSame(0, CashTransaction::count());
    }

    public function test_csrf_protects_create_update_and_delete(): void
    {
        $entry = $this->createEntry()->assertCreated();
        $this->app->instance('env', 'local');
        $token = str_repeat('c', 40);
        $this->withSession(['_token' => $token]);
        $this->createEntry()->assertStatus(419);
        $this->patchJson('/api/owner/cash-transactions/'.$entry->json('data.id'), ['version_token' => $entry->json('data.version_token')])->assertStatus(419);
        $this->deleteJson('/api/owner/cash-transactions/'.$entry->json('data.id'), ['version_token' => $entry->json('data.version_token')])->assertStatus(419);
        $this->deleteJson('/api/owner/cash-transactions/'.$entry->json('data.id'), ['version_token' => $entry->json('data.version_token')], ['X-CSRF-TOKEN' => $token])->assertNoContent();
    }

    private function createEntry(array $changes = [])
    {
        return $this->postJson('/api/owner/cash-transactions', array_merge([
            'transaction_type' => 'expense', 'amount' => '50000', 'occurred_at' => '2026-03-01 10:00:00', 'description' => 'Chi sửa chữa',
        ], $changes));
    }

    private function receipt(string $amount, string $at = '2026-03-01 09:00:00', ?Invoice $invoice = null): Payment
    {
        $invoice ??= $this->invoice();

        return Payment::create(['invoice_id' => $invoice->id, 'amount' => $amount, 'paid_at' => $at,
            'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid(), 'recorded_by' => $this->owner->id]);
    }

    private function invoice(): Invoice
    {
        $tag = (string) Str::uuid();
        $room = Room::create(['room_number' => 'C-'.(Room::count() + 1), 'capacity' => 2, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => $tag]);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '3000000']);
        $tenant = Tenant::create(['full_name' => 'Người thuê kiểm thử', 'phone_number' => '0900000000']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        $rep = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $invoice = Invoice::create(['invoice_number' => $tag, 'occupancy_id' => $occupancy->id, 'billing_month' => '2026-01-01',
            'representative_assignment_id' => $rep->id, 'issued_by' => $this->owner->id]);
        $invoice->issued_at = '2026-02-01';
        $invoice->save();
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id, 'item_type' => 'rent',
            'description' => 'Tiền phòng', 'unit' => 'tháng', 'quantity' => '1', 'unit_price' => '3000000']);

        return $invoice;
    }
}
