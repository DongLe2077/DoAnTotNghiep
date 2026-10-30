<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceItem;
use App\Models\Occupancy;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use App\Services\CashBook;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class PaymentCorrectionTest extends TestCase
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

    public function test_void_keeps_original_and_retry_key_but_reopens_debt_and_excludes_it_from_cash(): void
    {
        [$invoice] = $this->invoice();
        [$payment, $originalPayload] = $this->pay($invoice, '100');
        $before = Payment::findOrFail($payment['id'])->getAttributes();
        $this->postCorrection($payment, $this->payload($payment))->assertCreated()
            ->assertJsonPath('data.status', 'voided')->assertJsonPath('data.can_correct', false)
            ->assertJsonPath('replacement', null)->assertJsonPath('invoice.paid_amount', '0.00')
            ->assertJsonPath('invoice.outstanding_amount', '300.00')->assertJsonPath('invoice.payment_status', 'unpaid');
        $this->assertSame($before, Payment::findOrFail($payment['id'])->getAttributes());
        $this->assertSame('0.00', app(CashBook::class)->summary([])['invoice_payment_total']);
        $this->assertSame(0, app(CashBook::class)->ledger([])->count());
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/payments', $originalPayload)->assertOk()
            ->assertJsonPath('data.status', 'voided')->assertJsonPath('replayed', true)->assertJsonPath('invoice.paid_amount', '0.00');
        $this->pay($invoice, '300');
        $this->getJson('/api/owner/invoices/'.$invoice->id)->assertJsonPath('data.payment_status', 'paid');
        $this->assertSame(2, Payment::count());
        $this->assertSame(1, Payment::effective()->count());
    }

    public function test_atomic_replacement_updates_balances_reports_and_receipt_dates_without_mutating_original(): void
    {
        [$invoice] = $this->invoice();
        [$payment] = $this->pay($invoice, '100');
        $this->pay($invoice, '75');
        $before = Payment::findOrFail($payment['id'])->getAttributes();
        $values = $this->payload($payment, ['action' => 'replace', 'replacement' => ['amount' => '50',
            'paid_at' => '2026-02-28 09:00:00', 'payment_method' => 'bank_transfer']]);
        $response = $this->postCorrection($payment, $values)->assertCreated()
            ->assertJsonPath('data.status', 'voided')->assertJsonPath('replacement.amount', '50.00')
            ->assertJsonPath('replacement.replaces_payment_id', $payment['id'])
            ->assertJsonPath('invoice.paid_amount', '125.00')->assertJsonPath('invoice.outstanding_amount', '175.00');
        $this->assertSame($before, Payment::findOrFail($payment['id'])->getAttributes());
        $this->assertSame(3, Payment::count());
        $this->assertSame('125.00', app(CashBook::class)->summary([])['invoice_payment_total']);
        $this->assertSame('75.00', app(CashBook::class)->summary(['month' => '2026-03'])['invoice_payment_total']);
        $this->assertSame('50.00', app(CashBook::class)->summary(['month' => '2026-02'])['invoice_payment_total']);
        $this->getJson('/api/owner/dashboard?month=2026-03')->assertOk()
            ->assertJsonPath('data.cash_flow.invoice_payment_total', '75.00')
            ->assertJsonPath('data.receivables.paid_amount', '125.00');
        $this->getJson('/api/owner/payments?status=voided')->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/payments?status=active')->assertJsonCount(2, 'data');
        $this->assertSame($response->json('replacement.id'), PaymentCorrection::first()->replacement_payment_id);
    }

    public function test_exact_retry_precedes_stale_version_and_changed_payload_or_actor_is_rejected(): void
    {
        [$invoice] = $this->invoice();
        [$payment] = $this->pay($invoice, '100');
        $values = $this->payload($payment, ['action' => 'replace', 'replacement' => ['amount' => '50',
            'paid_at' => '2026-03-04 10:00:00', 'payment_method' => 'cash']]);
        $first = $this->postCorrection($payment, $values)->assertCreated();
        $this->pay($invoice, '25');
        $values['replacement']['amount'] = '50.00';
        $this->postCorrection($payment, $values)->assertOk()->assertJsonPath('replayed', true)
            ->assertJsonPath('replacement.id', $first->json('replacement.id'))->assertJsonPath('invoice.paid_amount', '75.00');
        $this->postCorrection($payment, array_replace($values, ['reason' => 'Lý do khác']))->assertConflict();
        $this->actingAs(User::factory()->create(), 'web');
        $this->postCorrection($payment, $values)->assertConflict();
        $this->assertSame(1, PaymentCorrection::count());
        $this->assertSame(3, Payment::count());
        $this->assertSame(1, AppNotification::count());
    }

    public function test_replacement_can_itself_be_corrected_but_old_receipts_cannot_be_voided_twice(): void
    {
        [$invoice] = $this->invoice();
        [$payment] = $this->pay($invoice, '100');
        $values = $this->payload($payment, ['action' => 'replace', 'replacement' => ['amount' => '50',
            'paid_at' => '2026-03-03 12:00:00', 'payment_method' => 'cash']]);
        $first = $this->postCorrection($payment, $values)->assertCreated();
        $this->postCorrection($payment, array_replace($values, ['idempotency_key' => (string) Str::uuid()]))->assertConflict();
        $replacement = $first->json('replacement');
        $this->postCorrection($replacement, $this->payload($replacement))->assertCreated()->assertJsonPath('invoice.paid_amount', '0.00');
        $this->postCorrection($payment, $values)->assertOk()->assertJsonPath('replacement.status', 'voided');
        $this->assertSame(2, PaymentCorrection::count());
        $this->assertSame(0, Payment::effective()->count());
    }

    public function test_overpayment_or_invalid_replacement_rolls_back_entire_correction(): void
    {
        [$invoice] = $this->invoice('100');
        [$payment] = $this->pay($invoice, '50');
        $this->pay($invoice, '20');
        $before = $invoice->fresh()->getAttributes();
        $this->postCorrection($payment, $this->payload($payment, ['action' => 'replace', 'replacement' => ['amount' => '90',
            'paid_at' => '2026-03-04 10:00:00', 'payment_method' => 'cash']]))->assertConflict();
        $this->assertSame(0, PaymentCorrection::count());
        $this->assertSame(2, Payment::count());
        $this->assertSame($before, $invoice->fresh()->getAttributes());
        $this->assertSame(0, AppNotification::count());
        $this->getJson('/api/owner/invoices/'.$invoice->id)->assertJsonPath('data.paid_amount', '70.00');
        $this->postCorrection($payment, $this->payload($payment, ['action' => 'replace', 'replacement' => ['amount' => '40',
            'paid_at' => '2026-01-01 10:00:00', 'payment_method' => 'cash']]))->assertUnprocessable()->assertJsonValidationErrors('replacement.paid_at');
        $this->assertSame(0, PaymentCorrection::count());
    }

    public function test_identical_replacement_is_rejected_without_fake_history(): void
    {
        [$invoice] = $this->invoice();
        [$payment] = $this->pay($invoice, '100');
        $this->postCorrection($payment, $this->payload($payment, ['action' => 'replace',
            'replacement' => array_intersect_key($payment, array_flip(['amount', 'paid_at', 'payment_method']))]))->assertUnprocessable();
        $this->assertSame(0, PaymentCorrection::count());
    }

    public function test_tenant_sees_history_and_notice_without_actor_or_request_secrets(): void
    {
        [$invoice, $tenant] = $this->invoice();
        [$payment] = $this->pay($invoice, '100');
        $ownerName = $this->owner->username;
        $this->postCorrection($payment, $this->payload($payment))->assertCreated();
        $this->owner->update(['username' => 'renamed_owner']);
        $this->getJson('/api/owner/payments/'.$payment['id'])->assertOk()
            ->assertJsonPath('data.correction.performed_by.username', $ownerName);
        $this->actingAs($tenant, 'web');
        $this->getJson('/api/tenant/invoices/'.$invoice->id)->assertOk()
            ->assertJsonPath('data.payments.0.status', 'voided')->assertJsonPath('data.paid_amount', '0.00')
            ->assertJsonMissingPath('data.payments.0.correction.performed_by')
            ->assertJsonMissingPath('data.payments.0.correction.before')
            ->assertJsonMissingPath('data.payments.0.version_token');
        $this->getJson('/api/notifications?type=invoice_updated')->assertOk()->assertJsonCount(1, 'data');
        $this->postCorrection($payment, [])->assertForbidden();
        $this->postJson('/api/owner/payments/999999/corrections', [])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->getJson('/api/tenant/invoices/'.$invoice->id)->assertNotFound();
        auth('web')->logout();
        $this->postCorrection($payment, [])->assertUnauthorized();
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_or_forged_payloads_do_not_create_history(array $change, string $field): void
    {
        [$invoice] = $this->invoice();
        [$payment] = $this->pay($invoice, '100');
        $this->postCorrection($payment, array_replace($this->payload($payment), $change))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, PaymentCorrection::count());
    }

    public static function invalidPayloads(): array
    {
        return [
            [['reason' => '   '], 'reason'], [['action' => 'refund'], 'action'], [['performed_by' => 1], 'performed_by'],
            [['replacement_payment_id' => 1], 'replacement_payment_id'], [['action' => 'replace'], 'replacement'],
            [['action' => 'replace', 'replacement' => ['amount' => '0', 'paid_at' => '2026-03-03 00:00:00', 'payment_method' => 'cash']], 'replacement.amount'],
            [['replacement' => ['amount' => '1']], 'replacement'], [['idempotency_key' => 'bad'], 'idempotency_key'],
            [['action' => 'replace', 'replacement' => ['amount' => '1', 'paid_at' => '2026-03-03 00:00:00', 'payment_method' => 'cash', 'recorded_by' => 1]], 'replacement'],
        ];
    }

    public function test_notification_failure_rolls_back_correction_replacement_and_invoice_revision(): void
    {
        [$invoice] = $this->invoice();
        [$payment] = $this->pay($invoice, '100');
        $before = $invoice->fresh()->getAttributes();
        DB::unprepared("CREATE TRIGGER reject_correction_notification BEFORE INSERT ON app_notifications BEGIN SELECT RAISE(ABORT, 'fixture notification failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->postCorrection($payment, $this->payload($payment, ['action' => 'replace', 'replacement' => ['amount' => '50',
                'paid_at' => '2026-03-03 12:00:00', 'payment_method' => 'cash']]));
            $this->fail('Expected transactional notification failure.');
        } catch (QueryException) {
            $this->assertSame(0, PaymentCorrection::count());
            $this->assertSame(1, Payment::count());
            $this->assertSame($before, $invoice->fresh()->getAttributes());
            $this->assertSame('100.00', app(CashBook::class)->summary([])['invoice_payment_total']);
        }
    }

    public function test_correction_requires_csrf_and_all_void_receipts_allow_invoice_cancellation(): void
    {
        [$invoice] = $this->invoice();
        [$payment] = $this->pay($invoice, '100');
        $values = $this->payload($payment);
        $this->app->instance('env', 'local');
        $token = str_repeat('b', 40);
        $this->withSession(['_token' => $token]);
        $this->postCorrection($payment, $values)->assertStatus(419);
        $this->postJson('/api/owner/payments/'.$payment['id'].'/corrections', $values, ['X-CSRF-TOKEN' => $token])->assertCreated();
        $state = $this->getJson('/api/owner/invoices/'.$invoice->id)->assertOk()->json('data');
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/changes', ['action' => 'cancel', 'reason' => 'Hủy sau đối chiếu các phiếu ghi nhầm',
            'version_token' => $state['version_token'], 'idempotency_key' => (string) Str::uuid()], ['X-CSRF-TOKEN' => $token])->assertOk();
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, PaymentCorrection::count());
    }

    public function test_internal_replacement_key_cannot_reuse_an_unrelated_existing_receipt(): void
    {
        [$invoice] = $this->invoice();
        [$payment] = $this->pay($invoice, '100');
        $key = (string) Str::uuid();
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/payments', ['amount' => '50', 'paid_at' => '2026-03-03 12:00:00',
            'payment_method' => 'cash', 'idempotency_key' => 'pc.'.substr(hash('sha256', $key), 0, 60)])->assertCreated();
        $this->postCorrection($payment, $this->payload($payment, ['idempotency_key' => $key, 'action' => 'replace',
            'replacement' => ['amount' => '50', 'paid_at' => '2026-03-03 12:00:00', 'payment_method' => 'cash']]))->assertConflict();
        $this->assertSame(0, PaymentCorrection::count());
        $this->assertSame(2, Payment::count());
    }

    private function payload(array $payment, array $changes = []): array
    {
        return array_replace(['action' => 'void', 'reason' => 'Đối chiếu phiếu thu ghi nhận nhầm', 'version_token' => $payment['version_token'],
            'idempotency_key' => (string) Str::uuid()], $changes);
    }

    private function postCorrection(array $payment, array $data)
    {
        return $this->postJson('/api/owner/payments/'.$payment['id'].'/corrections', $data);
    }

    private function pay(Invoice $invoice, string $amount): array
    {
        $payload = ['amount' => $amount, 'paid_at' => '2026-03-03 12:00:00', 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()];

        return [$this->postJson('/api/owner/invoices/'.$invoice->id.'/payments', $payload)->assertCreated()->json('data'), $payload];
    }

    private function invoice(string $amount = '300'): array
    {
        $tag = (string) Str::uuid();
        $room = Room::create(['room_number' => 'FIX-'.(Room::count() + 1), 'capacity' => 2, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => $tag]);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '3000000']);
        $tenant = Tenant::create(['full_name' => 'Người thuê đối chiếu', 'phone_number' => '0900000000']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        $assignment = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $invoice = Invoice::create(['invoice_number' => $tag, 'occupancy_id' => $occupancy->id, 'billing_month' => '2026-01-01',
            'representative_assignment_id' => $assignment->id, 'issued_by' => $this->owner->id]);
        $invoice->issued_at = '2026-02-01 00:00:00';
        $invoice->save();
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id, 'item_type' => 'rent',
            'description' => 'Tiền phòng', 'unit' => 'tháng', 'quantity' => '1', 'unit_price' => $amount]);
        InvoiceAccessGrant::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id, 'user_id' => $user->id,
            'representative_assignment_id' => $assignment->id, 'granted_by' => $this->owner->id, 'grant_reason' => 'Đối chiếu']);

        return [$invoice, $user];
    }
}
