<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceItem;
use App\Models\Occupancy;
use App\Models\Payment;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use App\Services\InvoicePayments;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use CreatesBillingSchema;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 5)->setTime(12, 0));
        $this->createBillingSchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
    }

    public function test_owner_can_record_partial_cash_then_pay_the_remainder_by_bank_transfer(): void
    {
        [$invoice] = $this->invoice();
        $first = $this->pay($invoice, ['amount' => '1000000'])->assertCreated()
            ->assertJsonPath('replayed', false)->assertJsonPath('data.amount', '1000000.00')
            ->assertJsonPath('data.recorded_by', $this->owner->id)->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('invoice.total_amount', '3000000.00')->assertJsonPath('invoice.paid_amount', '1000000.00')
            ->assertJsonPath('invoice.outstanding_amount', '2000000.00')->assertJsonPath('invoice.payment_status', 'partially_paid');
        $second = $this->pay($invoice, ['amount' => '2000000.00', 'payment_method' => 'bank_transfer'])->assertCreated()
            ->assertJsonPath('invoice.paid_amount', '3000000.00')->assertJsonPath('invoice.outstanding_amount', '0.00')
            ->assertJsonPath('invoice.payment_status', 'paid')->assertJsonCount(2, 'invoice.payments');
        $this->assertSame('PT-'.$first->json('data.id'), $first->json('data.receipt_number'));
        $this->getJson('/api/owner/payments/'.$second->json('data.id'))->assertOk()->assertJsonPath('data.invoice_id', $invoice->id);
        $this->getJson('/api/owner/invoices?payment_status=paid')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(2, Payment::count());
    }

    public function test_exact_replay_returns_the_same_receipt_even_after_invoice_is_fully_paid(): void
    {
        [$invoice] = $this->invoice('0.30');
        $values = $this->payload(['amount' => '0.10']);
        $first = $this->pay($invoice, $values)->assertCreated();
        $this->pay($invoice, ['amount' => '0.20'])->assertCreated()->assertJsonPath('invoice.payment_status', 'paid');
        $this->pay($invoice, $values)->assertOk()->assertJsonPath('replayed', true)
            ->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('invoice.paid_amount', '0.30');
        $this->assertSame(2, Payment::count());
    }

    public function test_numerically_equivalent_amount_is_the_same_retry_payload(): void
    {
        [$invoice] = $this->invoice();
        $values = $this->payload(['amount' => '1']);
        $first = $this->pay($invoice, $values)->assertCreated();
        $this->pay($invoice, array_merge($values, ['amount' => '1.00']))->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('replayed', true);
        $this->assertSame(1, Payment::count());
    }

    public function test_idempotency_key_cannot_be_reused_with_changed_amount_method_time_invoice_or_collector(): void
    {
        [$invoice] = $this->invoice();
        [$other] = $this->invoice();
        $values = $this->payload(['amount' => '100']);
        $this->pay($invoice, $values)->assertCreated();
        foreach ([['amount' => '101'], ['payment_method' => 'bank_transfer'], ['paid_at' => '2026-03-02 12:00:00']] as $change) {
            $this->pay($invoice, array_merge($values, $change))->assertConflict();
        }
        $this->pay($other, $values)->assertConflict();
        $this->actingAs(User::factory()->create(), 'web');
        $this->pay($invoice, $values)->assertConflict();
        $this->assertSame(1, Payment::count());
    }

    public function test_request_keys_preserve_ascii_case_as_in_mysql_ascii_bin_column(): void
    {
        [$invoice] = $this->invoice();
        $this->pay($invoice, ['amount' => '1', 'idempotency_key' => 'receipt-ABC'])->assertCreated();
        $this->pay($invoice, ['amount' => '1', 'idempotency_key' => 'receipt-abc'])->assertCreated();
        $this->assertSame(2, Payment::count());
    }

    public function test_decimal_cents_are_added_exactly_and_more_than_the_remainder_is_rejected(): void
    {
        [$invoice] = $this->invoice('0.30');
        $this->pay($invoice, ['amount' => '0.10'])->assertCreated()->assertJsonPath('invoice.outstanding_amount', '0.20');
        $this->pay($invoice, ['amount' => '0.21'])->assertConflict();
        $this->pay($invoice, ['amount' => '0.20'])->assertCreated()->assertJsonPath('invoice.outstanding_amount', '0.00');
        $this->pay($invoice, ['amount' => '0.01'])->assertConflict();
        $this->assertSame(2, Payment::count());
    }

    public function test_zero_total_or_empty_or_overpaid_invoice_cannot_receive_new_money(): void
    {
        [$zero] = $this->invoice('0');
        $this->pay($zero, ['amount' => '0.01'])->assertConflict();
        [$empty] = $this->invoice();
        $empty->items()->delete();
        $this->pay($empty)->assertConflict();
        [$overpaid] = $this->invoice('100');
        Payment::create(['invoice_id' => $overpaid->id, 'amount' => '101', 'paid_at' => '2026-03-01',
            'payment_method' => 'cash', 'idempotency_key' => 'imported-overpayment', 'recorded_by' => $this->owner->id]);
        $this->pay($overpaid)->assertConflict();
        $this->assertSame(1, Payment::count());
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payment_payloads_do_not_create_receipts(array $change, string $field): void
    {
        [$invoice] = $this->invoice();
        $this->pay($invoice, $change)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, Payment::count());
    }

    public static function invalidPayloads(): array
    {
        return [
            'zero' => [['amount' => '0'], 'amount'],
            'negative' => [['amount' => '-1'], 'amount'],
            'fractions' => [['amount' => '0.001'], 'amount'],
            'huge' => [['amount' => '10000000000000000'], 'amount'],
            'scientific string' => [['amount' => '1e3'], 'amount'],
            'thousands separator' => [['amount' => '1,000'], 'amount'],
            'array' => [['amount' => [1]], 'amount'],
            'future' => [['paid_at' => '2026-03-06 00:00:00'], 'paid_at'],
            'before issue' => [['paid_at' => '2026-01-01 00:00:00'], 'paid_at'],
            'invalid date' => [['paid_at' => '2026-02-30 00:00:00'], 'paid_at'],
            'date only' => [['paid_at' => '2026-03-01'], 'paid_at'],
            'method' => [['payment_method' => 'online'], 'payment_method'],
            'blank key' => [['idempotency_key' => ' '], 'idempotency_key'],
            'short key' => [['idempotency_key' => 'abc'], 'idempotency_key'],
            'long key' => [['idempotency_key' => str_repeat('x', 65)], 'idempotency_key'],
            'non ascii key' => [['idempotency_key' => 'phiếu-thu-123'], 'idempotency_key'],
            'collector spoof' => [['recorded_by' => 999], 'recorded_by'],
            'invoice spoof' => [['invoice_id' => 999], 'invoice_id'],
            'recording time spoof' => [['recorded_at' => '2026-01-01'], 'recorded_at'],
            'status spoof' => [['payment_status' => 'paid'], 'payment_status'],
        ];
    }

    public function test_required_fields_are_reported(): void
    {
        [$invoice] = $this->invoice();
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/payments', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'paid_at', 'payment_method', 'idempotency_key']);
    }

    public function test_filters_pagination_and_nested_invoice_scope(): void
    {
        [$one] = $this->invoice();
        [$two] = $this->invoice();
        $first = $this->pay($one, ['amount' => '1', 'paid_at' => '2026-03-01 00:00:00'])->assertCreated();
        $this->pay($two, ['amount' => '2', 'payment_method' => 'bank_transfer', 'paid_at' => '2026-03-02 00:00:00'])->assertCreated();
        $this->getJson('/api/owner/payments?invoice_id='.$one->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->json('data.id'));
        $this->getJson('/api/owner/invoices/'.$one->id.'/payments?invoice_id='.$two->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_id', $one->id);
        $this->getJson('/api/owner/payments?payment_method=bank_transfer')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/payments?room_id='.$one->occupancy->room_id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/payments?rental_group_id='.$two->occupancy->rental_group_id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/payments?paid_from=2026-03-02%2000:00:00')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/payments?paid_until=2026-03-01%2000:00:00')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/payments?paid_from=2026-03-01%2000:00:00&paid_until=2026-03-01%2000:00:00')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/payments?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/owner/payments?per_page=101&payment_method=wrong&paid_from=2026-03-02%2000:00:00&paid_until=2026-03-01%2000:00:00')->assertUnprocessable();
    }

    public function test_authorized_tenant_sees_updated_balance_but_cannot_record_or_list_owner_receipts(): void
    {
        [$invoice, $tenantUser, $assignment] = $this->invoice();
        InvoiceAccessGrant::create(['invoice_id' => $invoice->id, 'occupancy_id' => $invoice->occupancy_id,
            'user_id' => $tenantUser->id, 'representative_assignment_id' => $assignment->id,
            'granted_by' => $this->owner->id, 'grant_reason' => 'Xem hóa đơn kiểm thử']);
        $receipt = $this->pay($invoice, ['amount' => '100'])->assertCreated();
        $this->actingAs($tenantUser, 'web');
        $this->getJson('/api/tenant/invoices/'.$invoice->id)->assertOk()->assertJsonPath('data.paid_amount', '100.00')
            ->assertJsonPath('data.outstanding_amount', '2999900.00')->assertJsonPath('data.payments.0.amount', '100.00')
            ->assertJsonMissingPath('data.payments.0.idempotency_key')->assertJsonMissingPath('data.payments.0.recorded_by');
        $this->pay($invoice)->assertForbidden();
        $this->getJson('/api/owner/payments')->assertForbidden();
        $this->getJson('/api/owner/payments/'.$receipt->json('data.id'))->assertForbidden();
        $this->postJson('/api/owner/invoices/999999/payments', [])->assertForbidden();
        $this->assertSame(1, Payment::count());
    }

    public function test_guest_inactive_user_and_missing_records_are_handled(): void
    {
        $this->getJson('/api/owner/payments/999999')->assertNotFound();
        $this->getJson('/api/owner/invoices/999999/payments')->assertNotFound();
        $this->postJson('/api/owner/invoices/999999/payments', $this->payload())->assertNotFound();
        $this->owner->update(['is_active' => false]);
        $this->getJson('/api/owner/payments')->assertUnauthorized();
        auth('web')->logout();
        $this->postJson('/api/owner/invoices/999999/payments', [])->assertUnauthorized();
    }

    public function test_receipts_cannot_be_edited_or_deleted(): void
    {
        [$invoice] = $this->invoice();
        $id = $this->pay($invoice)->assertCreated()->json('data.id');
        $this->putJson('/api/owner/payments/'.$id, ['amount' => '1'])->assertStatus(405);
        $this->deleteJson('/api/owner/payments/'.$id)->assertStatus(405);
        $this->assertSame(1, Payment::count());
    }

    public function test_collecting_after_checkout_does_not_change_occupancy_or_invoices(): void
    {
        [$invoice] = $this->invoice();
        $invoice->occupancy->update(['ended_at' => '2026-02-01 00:00:00']);
        $before = $invoice->fresh()->getAttributes();
        $this->pay($invoice)->assertCreated();
        $this->assertSame($before, $invoice->fresh()->getAttributes());
        $this->assertNotNull($invoice->occupancy->fresh()->ended_at);
        $this->assertSame(1, InvoiceItem::count());
    }

    public function test_multiple_invoice_lines_and_receipts_do_not_multiply_balances(): void
    {
        [$invoice] = $this->invoice();
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $invoice->occupancy_id,
            'item_type' => 'service', 'description' => 'Phí', 'unit' => 'lần', 'quantity' => '1', 'unit_price' => '100']);
        $this->pay($invoice, ['amount' => '10'])->assertCreated();
        $this->pay($invoice, ['amount' => '20'])->assertCreated()->assertJsonPath('invoice.total_amount', '3000100.00')
            ->assertJsonPath('invoice.paid_amount', '30.00')->assertJsonPath('invoice.outstanding_amount', '3000070.00');
    }

    public function test_service_itself_validates_money_time_and_method(): void
    {
        [$invoice] = $this->invoice();
        foreach ([['amount' => '0.001'], ['paid_at' => '2026-02-30 00:00:00'], ['payment_method' => 'wrong'], ['idempotency_key' => 'bad']] as $change) {
            try {
                app(InvoicePayments::class)->record($invoice, $this->payload($change), $this->owner->id);
                $this->fail('Expected payment validation.');
            } catch (ValidationException) {
                $this->assertSame(0, Payment::count());
            }
        }
    }

    public function test_receipt_post_requires_csrf_when_browser_protection_is_enabled(): void
    {
        [$invoice] = $this->invoice();
        $this->app->instance('env', 'local');
        $token = str_repeat('a', 40);
        $this->withSession(['_token' => $token]);
        $this->pay($invoice)->assertStatus(419);
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/payments', $this->payload(), ['X-CSRF-TOKEN' => $token])->assertCreated();
        $this->assertSame(1, Payment::count());
    }

    private function payload(array $changes = []): array
    {
        return array_merge(['amount' => '1000000.00', 'paid_at' => '2026-03-01 12:00:00',
            'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()], $changes);
    }

    private function pay(Invoice $invoice, array $changes = [])
    {
        return $this->postJson('/api/owner/invoices/'.$invoice->id.'/payments', $this->payload($changes));
    }

    private function invoice(string $amount = '3000000'): array
    {
        $tag = (string) Str::uuid();
        $room = Room::create(['room_number' => 'P-'.(Room::count() + 1), 'capacity' => 2, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => $tag]);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '3000000']);
        $tenant = Tenant::create(['full_name' => 'Người thuê thử', 'phone_number' => '0900000000']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        $assignment = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $invoice = Invoice::create(['invoice_number' => $tag, 'occupancy_id' => $occupancy->id, 'billing_month' => '2026-01-01',
            'representative_assignment_id' => $assignment->id, 'issued_by' => $this->owner->id]);
        $invoice->issued_at = '2026-02-01 00:00:00';
        $invoice->save();
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id,
            'item_type' => 'rent', 'description' => 'Tiền phòng', 'unit' => 'tháng', 'quantity' => '1', 'unit_price' => $amount]);

        return [$invoice, $user, $assignment];
    }
}
