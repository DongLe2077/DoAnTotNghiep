<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceItem;
use App\Models\Occupancy;
use App\Models\Payment;
use App\Models\PayosCheckout;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use App\Services\PayosGateway;
use PayOS\Crypto\CryptoProvider;
use PayOS\Exceptions\PayOSException;
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class PayosInvoiceTest extends TestCase
{
    use CreatesBillingSchema;

    private const CHECKSUM = 'isolated-test-checksum';

    private const LINK = 'dea9570571224410a938a0e3ec7ce3cc';

    private Invoice $invoice;

    private User $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(9, 0));
        $this->createBillingSchema();
        (require database_path('migrations/2026_11_09_000001_create_payos_checkout_tables.php'))->up();
        config(['app.url' => 'https://rentbase.example.test', 'payos.enabled' => true,
            'payos.client_id' => 'isolated-test-client', 'payos.api_key' => 'isolated-test-api', 'payos.checksum_key' => self::CHECKSUM]);
        $this->owner = User::factory()->create();
        $tenant = Tenant::create(['full_name' => 'Người thuê kiểm thử', 'phone_number' => '0900000001']);
        $this->tenant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        $room = Room::create(['room_number' => 'PAYOS', 'capacity' => 1, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => 'PAYOS']);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-10-01', 'monthly_rent' => '3000000']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-10-01']);
        $assignment = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-10-01']);
        $this->invoice = Invoice::create(['invoice_number' => 'PAYOS-1', 'occupancy_id' => $occupancy->id, 'billing_month' => '2026-10-01',
            'representative_assignment_id' => $assignment->id, 'issued_by' => $this->owner->id]);
        InvoiceItem::create(['invoice_id' => $this->invoice->id, 'occupancy_id' => $occupancy->id, 'item_type' => 'rent',
            'description' => 'Tiền phòng', 'unit' => 'tháng', 'quantity' => '1', 'unit_price' => '3000000']);
        InvoiceAccessGrant::create(['invoice_id' => $this->invoice->id, 'occupancy_id' => $occupancy->id,
            'user_id' => $this->tenant->id, 'representative_assignment_id' => $assignment->id, 'granted_by' => $this->owner->id, 'grant_reason' => 'Kiểm thử']);
    }

    public function test_verified_transfer_pays_invoice_once_and_notifies_tenant(): void
    {
        $checkout = $this->checkout();
        $this->postJson($this->path())->assertOk()->assertJsonPath('data.checkout_url', 'https://pay.payos.vn/web/'.self::LINK);
        $this->assertDatabaseCount('invoice_payos_checkouts', 1);
        $event = $this->event($checkout);
        $tampered = $this->signed($event);
        $tampered['data']['amount']++;
        $this->postJson('/api/webhooks/payos', $tampered)->assertBadRequest();
        $this->assertDatabaseCount('payments', 0);
        $this->app->instance('env', 'local');
        $this->postJson('/api/webhooks/payos', $this->signed($event))->assertNoContent();
        $this->postJson('/api/webhooks/payos', $this->signed($event))->assertNoContent();
        $this->getJson($this->path())->assertOk()->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_id', Payment::sole()->id)->assertJsonPath('data.can_start', false);
        $this->getJson('/api/tenant/invoices/'.$this->invoice->id)->assertOk()->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.outstanding_amount', '0.00')->assertJsonPath('data.payments.0.source', 'payos');
        $this->assertDatabaseCount('invoice_payos_transfers', 1);
        $this->assertSame('Thanh toán thành công', AppNotification::sole()->data['title']);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_transfer_amount_mismatch_is_visible_to_owner_without_changing_balance(): void
    {
        $checkout = $this->checkout();
        $this->postJson('/api/webhooks/payos', $this->signed($this->event($checkout, ['amount' => 2000000])))->assertNoContent();
        $this->getJson($this->path())->assertOk()->assertJsonPath('data.status', 'review')->assertJsonPath('data.can_start', false);
        $this->actingAs($this->owner)->getJson('/api/owner/invoices/'.$this->invoice->id)->assertOk()
            ->assertJsonPath('data.payos_review_transfers.0.reference', 'BANK123')->assertJsonPath('data.outstanding_amount', '3000000.00');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_balance_change_while_link_is_pending_routes_transfer_to_review(): void
    {
        $checkout = $this->checkout();
        $this->actingAs($this->owner)->postJson('/api/owner/invoices/'.$this->invoice->id.'/payments', [
            'amount' => '1000000', 'paid_at' => now()->toDateTimeString(), 'payment_method' => 'cash', 'idempotency_key' => 'owner-cash-1',
        ])->assertCreated();
        $this->actingAs($this->tenant)->getJson($this->path())->assertOk()->assertJsonPath('data.status', 'stale')
            ->assertJsonPath('data.checkout_url', null);
        $this->postJson('/api/webhooks/payos', $this->signed($this->event($checkout)))->assertNoContent();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('invoice_payos_transfers', ['reference' => 'BANK123', 'payment_id' => null]);
    }

    public function test_recovery_reuses_lost_checkout_and_reconciles_a_missing_webhook(): void
    {
        $gateway = $this->partialMock(PayosGateway::class);
        $gateway->shouldReceive('create')->once()->andThrow(new PayOSException('Mất kết nối thử nghiệm'));
        $gateway->shouldReceive('get')->twice()->andReturnUsing(fn ($code) => [
            'id' => self::LINK, 'orderCode' => $code, 'amount' => 3000000,
            'transactions' => [['amount' => 3000000, 'reference' => 'BANK123', 'transactionDateTime' => now()->toDateTimeString()]],
        ]);
        $this->actingAs($this->tenant)->postJson($this->path())->assertOk()->assertJsonPath('data.checkout_url', 'https://pay.payos.vn/web/'.self::LINK);
        $this->artisan('rental:reconcile-payos')->assertSuccessful();
        $this->getJson($this->path())->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_unauthorized_tenant_and_fractional_balance_cannot_create_links(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'tenant']))->getJson($this->path())->assertNotFound();
        $this->postJson($this->path())->assertNotFound();
        $this->invoice->items()->update(['unit_price' => '3000000.50']);
        $this->actingAs($this->tenant)->getJson($this->path())->assertOk()->assertJsonPath('data.can_start', false);
        $this->postJson($this->path())->assertConflict();
        $this->assertDatabaseCount('invoice_payos_checkouts', 0);
    }

    public function test_expired_link_is_checked_before_a_second_link_can_be_created(): void
    {
        $checkout = $this->checkout();
        $paidAt = now()->toDateTimeString();
        $this->travel(16)->minutes();
        $gateway = $this->partialMock(PayosGateway::class);
        $gateway->shouldReceive('create')->never();
        $gateway->shouldReceive('get')->once()->with($checkout->order_code)->andReturn([
            'id' => self::LINK, 'orderCode' => $checkout->order_code, 'amount' => 3000000,
            'transactions' => [['amount' => 3000000, 'reference' => 'BANK123', 'transactionDateTime' => $paidAt]],
        ]);
        $this->postJson($this->path())->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertDatabaseCount('invoice_payos_checkouts', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_registration_sample_with_no_checkout_has_no_effect(): void
    {
        $this->postJson('/api/webhooks/payos', $this->signed($this->event(new PayosCheckout(['order_code' => 123]))))->assertNoContent();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('invoice_payos_transfers', 0);
    }

    public function test_signed_webhook_uses_the_rounded_remaining_balance_of_a_one_time_charge(): void
    {
        $path = '/api/owner/occupancies/'.$this->invoice->occupancy_id.'/invoices/one-time';
        $charge = ['billing_month' => '2026-10', 'description' => 'Phí thay thẻ ra vào', 'amount' => '2700',
            'idempotency_key' => 'one-time-payos-2700'];
        $token = $this->actingAs($this->owner, 'web')->postJson($path.'/preview', $charge)->assertOk()->json('data.preview_token');
        $id = $this->postJson($path, $charge + ['preview_token' => $token])->assertCreated()->assertJsonPath('data.total_amount', '3000.00')->json('data.id');
        $this->invoice = Invoice::findOrFail($id);
        $this->postJson('/api/owner/invoices/'.$id.'/payments', [
            'amount' => '1000', 'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'cash', 'idempotency_key' => 'one-time-partial-cash',
        ])->assertCreated()->assertJsonPath('invoice.outstanding_amount', '2000.00');

        $checkout = $this->checkout(2000);
        $event = $this->signed($this->event($checkout, ['amount' => 2000]));
        $this->postJson('/api/webhooks/payos', $event)->assertNoContent();
        $this->postJson('/api/webhooks/payos', $event)->assertNoContent();
        $this->getJson('/api/tenant/invoices/'.$id)->assertOk()->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.paid_amount', '3000.00')->assertJsonPath('data.outstanding_amount', '0.00');
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('invoice_payos_transfers', 1);
    }

    public function test_two_thousand_dong_one_time_invoice_creates_one_receipt_on_webhook_replay(): void
    {
        $path = '/api/owner/occupancies/'.$this->invoice->occupancy_id.'/invoices/one-time';
        $charge = ['billing_month' => '2026-10', 'description' => 'Phí sao chìa khóa', 'amount' => '2000',
            'idempotency_key' => 'one-time-payos-2000'];
        $token = $this->actingAs($this->owner, 'web')->postJson($path.'/preview', $charge)
            ->assertOk()->assertJsonPath('data.total_amount', '2000.00')->json('data.preview_token');
        $id = $this->postJson($path, $charge + ['preview_token' => $token])->assertCreated()
            ->assertJsonPath('data.outstanding_amount', '2000.00')->json('data.id');
        $this->invoice = Invoice::findOrFail($id);

        $checkout = $this->checkout(2000);
        $event = $this->signed($this->event($checkout, ['amount' => 2000]));
        $this->postJson('/api/webhooks/payos', $event)->assertNoContent();
        $this->postJson('/api/webhooks/payos', $event)->assertNoContent();
        $this->getJson('/api/tenant/invoices/'.$id)->assertOk()->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.paid_amount', '2000.00')->assertJsonPath('data.outstanding_amount', '0.00');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('invoice_payos_transfers', 1);
    }

    private function checkout(int $amount = 3000000): PayosCheckout
    {
        $this->partialMock(PayosGateway::class)->shouldReceive('create')->once()->andReturnUsing(function (array $request) use ($amount) {
            $this->assertSame($amount, $request['amount']);
            $this->assertSame('https://rentbase.example.test/tenant/invoices/'.$this->invoice->id.'?payos=return', $request['returnUrl']);

            return ['orderCode' => $request['orderCode'], 'amount' => $request['amount'],
                'paymentLinkId' => self::LINK, 'checkoutUrl' => 'https://pay.payos.vn/web/'.self::LINK];
        });
        $this->actingAs($this->tenant)->postJson($this->path(), ['amount' => 1])->assertOk()->assertJsonPath('data.status', 'pending');

        return PayosCheckout::query()->sole();
    }

    private function path(): string
    {
        return '/api/tenant/invoices/'.$this->invoice->id.'/payos';
    }

    private function event(PayosCheckout $checkout, array $changes = []): array
    {
        return array_replace(['code' => '00', 'currency' => 'VND', 'orderCode' => $checkout->order_code,
            'paymentLinkId' => self::LINK, 'amount' => 3000000, 'reference' => 'BANK123',
            'transactionDateTime' => now()->toDateTimeString()], $changes);
    }

    private function signed(array $data): array
    {
        return ['code' => '00', 'success' => true, 'data' => $data,
            'signature' => (new CryptoProvider)->createSignatureFromObj($data, self::CHECKSUM)];
    }
}
