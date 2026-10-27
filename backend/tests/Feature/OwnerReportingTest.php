<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class OwnerReportingTest extends TestCase
{
    use CreatesCashBookSchema;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(12, 0));
        $this->createCashBookSchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
    }

    public function test_empty_reports_have_zero_counts_and_decimal_strings(): void
    {
        $this->getJson('/api/owner/dashboard')->assertOk()->assertJsonPath('data.month', '2026-03')
            ->assertJsonPath('data.rooms.total_count', 0)->assertJsonPath('data.tenants.residing_count', 0)
            ->assertJsonPath('data.maintenance.open_count', 0)->assertJsonPath('data.receivables.outstanding_amount', '0.00')
            ->assertJsonPath('data.cash_flow.total_income', '0.00')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/owner/receivables')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/owner/receivables/groups')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/owner/receivables/summary')->assertOk()->assertJsonPath('data.invoice_count', 0)
            ->assertJsonPath('data.paid_amount', '0.00');
    }

    public function test_receivables_keep_closed_stays_and_exclude_paid_and_inconsistent_invoices(): void
    {
        $unpaid = $this->invoice('100.10');
        $partial = $this->invoice('250.25');
        $this->pay($partial, '50.05');
        $this->pay($partial, '25.10');
        $paid = $this->invoice('80');
        $this->pay($paid, '80');
        $this->invoice('0');
        $empty = $this->invoice(null);
        $overpaid = $this->invoice('10');
        $this->pay($overpaid, '11');
        $closed = $this->invoice('40');
        $this->close($closed->occupancy);

        $response = $this->getJson('/api/owner/receivables/summary')->assertOk()
            ->assertJsonPath('data.invoice_count', 7)->assertJsonPath('data.valid_invoice_count', 5)
            ->assertJsonPath('data.open_invoice_count', 3)->assertJsonPath('data.unpaid_count', 2)
            ->assertJsonPath('data.partially_paid_count', 1)->assertJsonPath('data.paid_count', 2)
            ->assertJsonPath('data.inconsistent_count', 2)->assertJsonPath('data.outstanding_room_count', 3)
            ->assertJsonPath('data.total_amount', '470.35')->assertJsonPath('data.paid_amount', '155.15')
            ->assertJsonPath('data.outstanding_amount', '315.20');
        $list = $this->getJson('/api/owner/receivables?per_page=2')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(2, 'data');
        $all = $this->getJson('/api/owner/receivables')->assertOk();
        $this->assertEqualsCanonicalizing([$unpaid->id, $partial->id, $closed->id], array_column($all->json('data'), 'id'));
        $this->assertNotContains($empty->id, array_column($list->json('data'), 'id'));
        $this->assertNotContains($overpaid->id, array_column($all->json('data'), 'id'));
        $this->assertSame('315.20', $response->json('data.outstanding_amount'));
    }

    public function test_summary_and_groups_apply_month_room_group_and_occupancy_filters(): void
    {
        $first = $this->invoice('100.25', '2026-01-01');
        $second = $this->invoice('200.50', '2026-02-01', $first->occupancy);
        $this->pay($second, '50.25');
        $other = $this->invoice('999');
        $this->getJson('/api/owner/receivables/summary?billing_month=2026-01')->assertOk()
            ->assertJsonPath('data.invoice_count', 1)->assertJsonPath('data.outstanding_amount', '100.25');
        foreach (['room_id' => $first->occupancy->room_id, 'occupancy_id' => $first->occupancy_id,
            'rental_group_id' => $first->occupancy->rental_group_id] as $key => $value) {
            $this->getJson('/api/owner/receivables/summary?'.$key.'='.$value)->assertOk()
                ->assertJsonPath('data.invoice_count', 2)->assertJsonPath('data.outstanding_amount', '250.50');
            $this->getJson('/api/owner/receivables?'.$key.'='.$value)->assertOk()->assertJsonCount(2, 'data');
        }
        $this->getJson('/api/owner/receivables/groups?group_by=room&room_id='.$first->occupancy->room_id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.group_id', $first->occupancy->room_id)
            ->assertJsonPath('data.0.invoice_count', 2)->assertJsonPath('data.0.total_amount', '300.75')
            ->assertJsonPath('data.0.paid_amount', '50.25')->assertJsonPath('data.0.outstanding_amount', '250.50');
        $this->getJson('/api/owner/receivables/groups?group_by=rental_group&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.group_id', $other->occupancy->rental_group_id)
            ->assertJsonPath('data.0.group_by', 'rental_group');
        $this->getJson('/api/owner/receivables/summary?room_id=999999')->assertJsonPath('data.invoice_count', 0);
        $this->getJson('/api/owner/receivables?billing_month=2026-01&room_id='.$other->occupancy->room_id)->assertJsonCount(0, 'data');
    }

    public function test_group_debt_follows_the_same_group_across_rooms_without_double_counting(): void
    {
        $old = $this->invoice('100');
        $this->close($old->occupancy);
        $newOccupancy = $this->context($old->occupancy->rental_group_id);
        $this->invoice('200', '2026-03-01', $newOccupancy);
        $this->getJson('/api/owner/receivables/groups?group_by=rental_group')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_count', 2)->assertJsonPath('data.0.outstanding_amount', '300.00');
        $this->getJson('/api/owner/receivables/groups?group_by=room')->assertJsonCount(2, 'data');
        $this->getJson('/api/owner/receivables/summary')->assertJsonPath('data.outstanding_group_count', 1)
            ->assertJsonPath('data.outstanding_room_count', 2);
    }

    public function test_more_than_one_line_and_receipt_does_not_multiply_money(): void
    {
        $invoice = $this->invoice('100');
        // A second line exercises aggregation separately from the two receipts.
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $invoice->occupancy_id,
            'item_type' => 'service', 'description' => 'Dịch vụ', 'unit' => 'phòng', 'quantity' => '1', 'unit_price' => '20']);
        $this->pay($invoice, '10');
        $this->pay($invoice, '30');
        $this->getJson('/api/owner/receivables/summary')->assertOk()->assertJsonPath('data.total_amount', '120.00')
            ->assertJsonPath('data.paid_amount', '40.00')->assertJsonPath('data.outstanding_amount', '80.00');
        $this->getJson('/api/owner/receivables/groups')->assertJsonPath('data.0.outstanding_amount', '80.00');
    }

    public function test_dashboard_keeps_operating_state_current_and_cash_period_separate_from_billing_month(): void
    {
        $old = $this->invoice('100.50', '2026-01-01');
        $this->pay($old, '25.25', '2026-03-01 10:00:00');
        $this->pay($old, '10', '2026-02-01 10:00:00');
        $this->close($old->occupancy);
        $active = $this->invoice('200', '2026-03-01');
        $active->occupancy->room->update(['operational_status' => 'maintenance']);
        $this->room();
        $withoutResidents = $this->context();
        $withoutResidents->tenantStays()->update(['moved_out_at' => '2026-03-01 00:00:00']);
        foreach (['submitted', 'in_progress', 'resolved'] as $status) {
            MaintenanceRequest::create(['occupancy_id' => $old->occupancy_id, 'submitted_by' => $this->owner->id,
                'representative_assignment_id' => $old->representative_assignment_id, 'title' => 'Lỗi', 'description' => 'Mô tả',
                'status' => $status, 'resolution' => $status === 'resolved' ? 'Đã sửa' : null]);
        }
        CashTransaction::create(['transaction_type' => 'other_income', 'amount' => '5.25', 'occurred_at' => '2026-03-01',
            'description' => 'Thu khác', 'recorded_by' => $this->owner->id]);
        CashTransaction::create(['transaction_type' => 'expense', 'amount' => '7.10', 'occurred_at' => '2026-03-01',
            'description' => 'Chi', 'recorded_by' => $this->owner->id]);

        $this->getJson('/api/owner/dashboard?month=2026-03')->assertOk()
            ->assertJsonPath('data.rooms.total_count', 4)->assertJsonPath('data.rooms.maintenance_count', 1)
            ->assertJsonPath('data.rooms.vacant_count', 3)->assertJsonPath('data.rooms.occupied_count', 0)
            ->assertJsonPath('data.rooms.active_occupancy_count', 2)->assertJsonPath('data.rooms.available_for_check_in_count', 2)
            ->assertJsonPath('data.rooms.resident_count', 1)->assertJsonPath('data.tenants.residing_count', 1)
            ->assertJsonPath('data.maintenance.open_count', 2)->assertJsonPath('data.maintenance.resolved_count', 1)
            ->assertJsonPath('data.invoices.total_amount', '200.00')->assertJsonPath('data.receivables.outstanding_amount', '265.25')
            ->assertJsonPath('data.cash_flow.invoice_payment_total', '25.25')->assertJsonPath('data.cash_flow.total_income', '30.50')
            ->assertJsonPath('data.cash_flow.expense_total', '7.10')->assertJsonPath('data.cash_flow.net_cash_flow', '23.40');
        $this->getJson('/api/owner/dashboard?month=2026-01')->assertJsonPath('data.invoices.total_amount', '100.50')
            ->assertJsonPath('data.invoices.paid_amount', '35.25')->assertJsonPath('data.cash_flow.total_income', '0.00')
            ->assertJsonPath('data.rooms.maintenance_count', 1);
    }

    public function test_financial_summary_retains_exact_cents_at_large_amounts(): void
    {
        $this->invoice('123456789012.34');
        $this->invoice('0.01');
        $this->getJson('/api/owner/receivables/summary')->assertOk()->assertJsonPath('data.outstanding_amount', '123456789012.35');
    }

    public function test_reporting_filters_validate_months_ids_and_pagination(): void
    {
        foreach (['month=2026-13', 'month=0000-01', 'month[]=2026-03'] as $query) {
            $this->getJson('/api/owner/dashboard?'.$query)->assertUnprocessable();
        }
        foreach (['billing_month=0000-01', 'billing_month=2026-02-01', 'room_id=0', 'rental_group_id=-1',
            'occupancy_id[]=1', 'per_page=101', 'page=0', 'group_by=tenant'] as $query) {
            $this->getJson('/api/owner/receivables/summary?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/owner/dashboard?month=9999-12')->assertOk()->assertJsonPath('data.invoices.invoice_count', 0);
        $this->getJson('/api/owner/receivables?billing_month=9999-12')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_active_owners_can_access_all_reports(): void
    {
        $paths = ['/api/owner/dashboard', '/api/owner/receivables', '/api/owner/receivables/summary', '/api/owner/receivables/groups'];
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        foreach ($paths as $path) {
            $this->getJson($path)->assertForbidden();
        }
        $this->actingAs($this->owner, 'web');
        $this->owner->update(['is_active' => false]);
        foreach ($paths as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
    }

    public function test_monthly_charts_fill_empty_months_and_handle_year_boundaries(): void
    {
        $response = $this->getJson('/api/owner/dashboard?month=2026-03')->assertOk()->assertJsonCount(6, 'data.monthly_series');
        $this->assertSame(['2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03'], array_column($response->json('data.monthly_series'), 'month'));
        foreach ($response->json('data.monthly_series') as $point) {
            $this->assertSame(0, $point['invoice_count']);
            foreach (['invoice_total', 'invoice_paid', 'invoice_outstanding', 'cash_income', 'cash_expense', 'cash_net'] as $key) {
                $this->assertSame('0.00', $point[$key]);
            }
        }
        $this->getJson('/api/owner/dashboard?month=1000-01')->assertOk()->assertJsonCount(1, 'data.monthly_series')
            ->assertJsonPath('data.monthly_series.0.month', '1000-01');
        $this->getJson('/api/owner/dashboard?month=9999-12')->assertOk()->assertJsonCount(6, 'data.monthly_series')
            ->assertJsonPath('data.monthly_series.5.month', '9999-12');
    }

    public function test_monthly_charts_keep_billing_period_and_cash_date_distinct_and_exclude_bad_or_deleted_money(): void
    {
        $old = $this->invoice('100.10', '2026-01-01');
        $this->pay($old, '25.05', '2026-03-01 10:00:00');
        $this->invoice('200.20', '2026-03-01');
        $bad = $this->invoice('10.00', '2026-02-01');
        $this->pay($bad, '11.00', '2026-02-01 10:00:00');
        $this->invoice('55.00', '2025-09-01');
        $this->invoice('77.00', '2026-04-01');
        CashTransaction::create(['transaction_type' => 'other_income', 'amount' => '5.00', 'occurred_at' => '2026-01-02', 'description' => 'Thu khác', 'recorded_by' => $this->owner->id]);
        CashTransaction::create(['transaction_type' => 'expense', 'amount' => '40.00', 'occurred_at' => '2026-03-02', 'description' => 'Chi', 'recorded_by' => $this->owner->id]);
        $deleted = CashTransaction::create(['transaction_type' => 'expense', 'amount' => '900.00', 'occurred_at' => '2026-03-02', 'description' => 'Đã xóa', 'recorded_by' => $this->owner->id]);
        $deleted->delete();
        $response = $this->getJson('/api/owner/dashboard?month=2026-03')->assertOk();
        $series = collect($response->json('data.monthly_series'))->keyBy('month');
        $this->assertSame('100.10', $series['2026-01']['invoice_total']);
        $this->assertSame('25.05', $series['2026-01']['invoice_paid']);
        $this->assertSame('75.05', $series['2026-01']['invoice_outstanding']);
        $this->assertSame('5.00', $series['2026-01']['cash_income']);
        $this->assertSame(1, $series['2026-02']['inconsistent_count']);
        $this->assertSame('0.00', $series['2026-02']['invoice_total']);
        $this->assertSame('11.00', $series['2026-02']['cash_income']);
        $this->assertSame('200.20', $series['2026-03']['invoice_total']);
        $this->assertSame('0.00', $series['2026-03']['invoice_paid']);
        $this->assertSame('25.05', $series['2026-03']['cash_income']);
        $this->assertSame('40.00', $series['2026-03']['cash_expense']);
        $this->assertSame('-14.95', $series['2026-03']['cash_net']);
        $this->assertFalse($series->has('2025-09'));
        $this->assertFalse($series->has('2026-04'));
        $this->assertSame($response->json('data.invoices.total_amount'), $series['2026-03']['invoice_total']);
        $this->assertSame($response->json('data.cash_flow.total_income'), $series['2026-03']['cash_income']);
    }

    public function test_monthly_chart_totals_do_not_multiply_lines_or_payments_and_retain_cents(): void
    {
        $invoice = $this->invoice('123456789012.34', '2026-03-01');
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $invoice->occupancy_id,
            'item_type' => 'service', 'description' => 'Dịch vụ', 'unit' => 'phòng', 'quantity' => '1', 'unit_price' => '0.01']);
        $this->pay($invoice, '10.10');
        $this->pay($invoice, '20.20');
        $this->getJson('/api/owner/dashboard?month=2026-03')->assertOk()
            ->assertJsonPath('data.monthly_series.5.invoice_count', 1)
            ->assertJsonPath('data.monthly_series.5.invoice_total', '123456789012.35')
            ->assertJsonPath('data.monthly_series.5.invoice_paid', '30.30')
            ->assertJsonPath('data.monthly_series.5.cash_income', '30.30');
    }

    private function room(): Room
    {
        return Room::create(['room_number' => 'R-'.(Room::count() + 1), 'capacity' => 2,
            'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
    }

    private function context(?int $groupId = null): Occupancy
    {
        $occupancy = Occupancy::create(['room_id' => $this->room()->id,
            'rental_group_id' => $groupId ?? RentalGroup::create(['group_code' => (string) Str::uuid()])->id,
            'started_at' => '2026-01-01', 'monthly_rent' => '3000000']);
        $tenant = Tenant::create(['full_name' => 'Người kiểm thử', 'phone_number' => '0900000000']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);

        return $occupancy;
    }

    private function invoice(?string $total, string $month = '2026-02-01', ?Occupancy $occupancy = null): Invoice
    {
        $occupancy ??= $this->context();
        $invoice = Invoice::create(['invoice_number' => (string) Str::uuid(), 'occupancy_id' => $occupancy->id,
            'billing_month' => $month, 'representative_assignment_id' => $occupancy->representativeAssignments()->first()->id,
            'issued_by' => $this->owner->id]);
        if ($total !== null) {
            InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id,
                'item_type' => 'rent', 'description' => 'Tiền phòng', 'unit' => 'tháng', 'quantity' => '1', 'unit_price' => $total]);
        }

        return $invoice;
    }

    private function pay(Invoice $invoice, string $amount, string $at = '2026-03-01 10:00:00'): void
    {
        Payment::create(['invoice_id' => $invoice->id, 'amount' => $amount, 'paid_at' => $at,
            'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid(), 'recorded_by' => $this->owner->id]);
    }

    private function close(Occupancy $occupancy): void
    {
        $occupancy->tenantStays()->update(['moved_out_at' => '2026-03-01 00:00:00']);
        $occupancy->representativeAssignments()->update(['ended_at' => '2026-03-01 00:00:00']);
        $occupancy->update(['ended_at' => '2026-03-01 00:00:00']);
    }
}
