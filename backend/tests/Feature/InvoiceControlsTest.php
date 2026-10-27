<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceChange;
use App\Models\InvoiceItem;
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

class InvoiceControlsTest extends TestCase
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

    public function test_old_invoice_without_deadline_is_not_automatically_overdue(): void
    {
        [$invoice] = $this->invoice();
        $this->state($invoice)->assertJsonPath('data.due_date', null)->assertJsonPath('data.due_status', 'not_set')->assertJsonPath('data.overdue_days', 0);
        $this->getJson('/api/owner/receivables?due_status=overdue')->assertJsonCount(0, 'data');
        $this->getJson('/api/owner/receivables?due_status=not_set')->assertJsonCount(1, 'data');
    }

    public function test_deadline_is_inclusive_and_overdue_tracks_only_the_remaining_debt(): void
    {
        [$invoice] = $this->invoice('300.30');
        $this->change($invoice, ['action' => 'due_date', 'due_date' => '2026-03-05'])->assertOk()->assertJsonPath('data.due_status', 'upcoming');
        $this->pay($invoice, '100.10')->assertCreated();
        $this->travelTo(now()->setDate(2026, 3, 6)->startOfDay());
        $this->state($invoice)->assertJsonPath('data.due_status', 'overdue')->assertJsonPath('data.overdue_days', 1)->assertJsonPath('data.outstanding_amount', '200.20');
        $this->getJson('/api/owner/receivables/summary')->assertJsonPath('data.overdue_count', 1)->assertJsonPath('data.overdue_amount', '200.20');
        $this->getJson('/api/owner/receivables/groups?due_status=overdue&group_by=room')->assertJsonCount(1, 'data')->assertJsonPath('data.0.outstanding_amount', '200.20');
        $this->pay($invoice, '200.20')->assertCreated();
        $this->state($invoice)->assertJsonPath('data.due_status', 'paid');
        $this->getJson('/api/owner/receivables/summary')->assertJsonPath('data.overdue_count', 0)->assertJsonPath('data.overdue_amount', '0.00');
    }

    public function test_deadline_extension_is_audited_and_exact_retry_works_after_midnight(): void
    {
        [$invoice] = $this->invoice();
        $payload = $this->payload($invoice, ['action' => 'due_date', 'due_date' => '2026-03-05']);
        $this->postChange($invoice, $payload)->assertOk()->assertJsonPath('replayed', false);
        $this->travelTo(now()->addDays(2));
        $this->postChange($invoice, $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonCount(1, 'data.changes');
        $this->change($invoice, ['action' => 'due_date', 'due_date' => '2026-03-06'])->assertUnprocessable();
        $this->change($invoice, ['action' => 'due_date', 'due_date' => '2026-03-20'])->assertOk()->assertJsonPath('data.due_status', 'upcoming')
            ->assertJsonPath('data.changes.1.before.due_date', '2026-03-05')->assertJsonPath('data.changes.1.after.due_date', '2026-03-20');
    }

    public function test_adjustments_change_invoice_balance_but_never_cash_or_original_lines(): void
    {
        [$invoice] = $this->invoice('0.30');
        $this->change($invoice, ['amount' => '0.10'])->assertOk()->assertJsonPath('data.total_amount', '0.40')->assertJsonPath('data.original_amount', '0.30');
        $this->pay($invoice, '0.10')->assertCreated();
        $this->change($invoice, ['amount' => '-0.20'])->assertOk()->assertJsonPath('data.total_amount', '0.20')
            ->assertJsonPath('data.adjustment_amount', '-0.10')->assertJsonPath('data.outstanding_amount', '0.10');
        $this->pay($invoice, '0.11')->assertConflict();
        $this->getJson('/api/owner/receivables/summary')->assertJsonPath('data.total_amount', '0.20')->assertJsonPath('data.paid_amount', '0.10');
        $this->getJson('/api/owner/cash-flow/summary')->assertJsonPath('data.invoice_payment_total', '0.10');
        $this->assertSame('0.3000', $invoice->items()->first()->unit_price);
        $this->assertSame(1, Payment::count());
    }

    public function test_paid_invoice_can_be_increased_but_cannot_be_reduced_below_collected_amount_or_cancelled(): void
    {
        [$invoice] = $this->invoice('100');
        $this->pay($invoice, '100')->assertCreated();
        $this->change($invoice, ['amount' => '-0.01'])->assertConflict();
        $this->change($invoice, ['action' => 'cancel'])->assertConflict();
        $this->change($invoice, ['amount' => '20'])->assertOk()->assertJsonPath('data.payment_status', 'partially_paid')->assertJsonPath('data.outstanding_amount', '20.00');
        $this->change($invoice, ['amount' => '-20'])->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->assertSame(2, InvoiceChange::count());
    }

    public function test_cancellation_and_restoration_preserve_history_permissions_sources_and_notifications(): void
    {
        [$invoice, $tenant] = $this->invoice('100');
        $this->change($invoice, ['amount' => '-20'])->assertOk();
        $this->change($invoice, ['action' => 'cancel'])->assertOk()->assertJsonPath('data.payment_status', 'cancelled')
            ->assertJsonPath('data.total_amount', '0.00')->assertJsonPath('data.original_amount', '100.00');
        $this->pay($invoice, '1')->assertConflict();
        $this->change($invoice, ['amount' => '1'])->assertConflict();
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/reminder')->assertConflict();
        $this->getJson('/api/owner/receivables/summary')->assertJsonPath('data.cancelled_count', 1)->assertJsonPath('data.valid_invoice_count', 0)->assertJsonPath('data.outstanding_amount', '0.00');
        $this->actingAs($tenant, 'web');
        $this->getJson('/api/tenant/invoices/'.$invoice->id)->assertOk()->assertJsonPath('data.payment_status', 'cancelled')
            ->assertJsonCount(2, 'data.changes')->assertJsonMissingPath('data.version_token');
        $this->getJson('/api/notifications?type=invoice_updated')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->owner, 'web');
        $this->change($invoice, ['action' => 'restore'])->assertOk()->assertJsonPath('data.total_amount', '80.00')->assertJsonPath('data.payment_status', 'unpaid');
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame(1, $invoice->accessGrants()->whereNull('revoked_at')->count());
    }

    public function test_retries_do_not_repeat_adjustments_and_stale_balance_or_changed_request_is_rejected(): void
    {
        [$invoice] = $this->invoice('100');
        $payload = $this->payload($invoice, ['amount' => '-10']);
        $this->postChange($invoice, $payload)->assertOk();
        $this->pay($invoice, '20')->assertCreated();
        $this->postChange($invoice, $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.total_amount', '90.00');
        $this->postChange($invoice, array_replace($payload, ['reason' => 'Nội dung khác']))->assertConflict();
        $this->postChange($invoice, array_replace($payload, ['idempotency_key' => (string) Str::uuid()]))->assertConflict();
        $fresh = $this->payload($invoice);
        $this->pay($invoice, '10')->assertCreated();
        $this->postChange($invoice, $fresh)->assertConflict();
        $this->assertSame(1, InvoiceChange::count());
    }

    public function test_invalid_values_and_other_roles_cannot_change_invoices(): void
    {
        [$invoice, $tenant] = $this->invoice('100');
        foreach ([['amount' => '0'], ['amount' => '-0.00'], ['amount' => '0.001'], ['amount' => '1e2'], ['reason' => '  '],
            ['action' => 'delete'], ['action' => 'due_date', 'due_date' => '2026-02-30'], ['action' => 'due_date', 'due_date' => '0000-01-01']] as $invalid) {
            $this->change($invoice, $invalid)->assertUnprocessable();
        }
        $this->change($invoice, ['amount' => '-101'])->assertConflict();
        $this->change($invoice, ['amount' => '9999999999999999.99'])->assertUnprocessable();
        $this->actingAs($tenant, 'web');
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/changes', [])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->getJson('/api/tenant/invoices/'.$invoice->id)->assertNotFound();
        $this->assertSame(0, InvoiceChange::count());
    }

    public function test_scheduled_reminders_only_target_overdue_debts_and_do_not_duplicate(): void
    {
        [$invoice, $tenant] = $this->invoice('100');
        $this->change($invoice, ['action' => 'due_date', 'due_date' => '2026-03-05'])->assertOk();
        $this->artisan('rental:remind-invoices --overdue')->expectsOutputToContain('"candidates":0')->assertSuccessful();
        $this->travelTo(now()->addDay());
        $this->artisan('rental:remind-invoices --overdue')->expectsOutputToContain('"created":1')->assertSuccessful();
        $this->artisan('rental:remind-invoices --overdue')->expectsOutputToContain('"replayed":1')->assertSuccessful();
        $this->actingAs($tenant, 'web');
        $this->getJson('/api/notifications?type=invoice_reminder')->assertJsonCount(1, 'data')->assertJsonPath('data.0.data.title', 'Hóa đơn đã quá hạn thanh toán');
    }

    private function state(Invoice $invoice)
    {
        return $this->getJson('/api/owner/invoices/'.$invoice->id)->assertOk();
    }

    private function payload(Invoice $invoice, array $values = []): array
    {
        $result = array_replace(['action' => 'adjustment', 'amount' => '1', 'reason' => 'Đối chiếu và sửa khoản thu',
            'version_token' => $this->state($invoice)->json('data.version_token'), 'idempotency_key' => (string) Str::uuid()], $values);
        if ($result['action'] !== 'adjustment') {
            unset($result['amount']);
        }

        return $result;
    }

    private function change(Invoice $invoice, array $values = [])
    {
        return $this->postChange($invoice, $this->payload($invoice, $values));
    }

    private function postChange(Invoice $invoice, array $payload)
    {
        return $this->postJson('/api/owner/invoices/'.$invoice->id.'/changes', $payload);
    }

    private function pay(Invoice $invoice, string $amount)
    {
        return $this->postJson('/api/owner/invoices/'.$invoice->id.'/payments', ['amount' => $amount, 'paid_at' => now()->format('Y-m-d H:i:s'),
            'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()]);
    }

    private function invoice(string $amount = '3000000'): array
    {
        $tag = (string) Str::uuid();
        $room = Room::create(['room_number' => 'CTRL-'.(Room::count() + 1), 'capacity' => 2, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => $tag]);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '3000000']);
        $tenant = Tenant::create(['full_name' => 'Người thuê nghiệm thu', 'phone_number' => '0900000000']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        $assignment = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $invoice = Invoice::create(['invoice_number' => $tag, 'occupancy_id' => $occupancy->id, 'billing_month' => '2026-01-01',
            'representative_assignment_id' => $assignment->id, 'issued_by' => $this->owner->id]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id,
            'item_type' => 'rent', 'description' => 'Tiền phòng', 'unit' => 'tháng', 'quantity' => '1', 'unit_price' => $amount]);
        InvoiceAccessGrant::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id, 'user_id' => $user->id,
            'representative_assignment_id' => $assignment->id, 'granted_by' => $this->owner->id, 'grant_reason' => 'Nghiệm thu']);

        return [$invoice, $user];
    }
}
