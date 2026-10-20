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
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class InvoiceAccessTest extends TestCase
{
    use CreatesBillingSchema;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBillingSchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
    }

    public function test_owner_reads_invoice_details_and_balances_without_multiplying_payments(): void
    {
        [$invoice] = $this->invoice();
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $invoice->occupancy_id,
            'item_type' => 'service', 'description' => 'Phí dịch vụ', 'unit' => 'tháng', 'quantity' => '1', 'unit_price' => '100000']);
        foreach (['100000', '200000'] as $index => $amount) {
            Payment::create(['invoice_id' => $invoice->id, 'amount' => $amount, 'paid_at' => '2026-02-01',
                'payment_method' => 'cash', 'idempotency_key' => 'receipt-'.$index, 'recorded_by' => $this->owner->id]);
        }
        $this->getJson('/api/owner/invoices/'.$invoice->id)->assertOk()
            ->assertJsonPath('data.total_amount', '3100000.00')->assertJsonPath('data.paid_amount', '300000.00')
            ->assertJsonPath('data.outstanding_amount', '2800000.00')->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonCount(2, 'data.items')->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.items.0.quantity', '1.000')->assertJsonPath('data.items.0.unit_price', '3000000.0000')
            ->assertJsonMissingPath('data.payments.0.idempotency_key')->assertJsonMissingPath('data.password_hash');
    }

    public function test_invoice_filters_and_pagination_use_validated_inputs(): void
    {
        [$first] = $this->invoice();
        [$second] = $this->invoice(month: '2026-02-01');
        $this->getJson('/api/owner/invoices?billing_month=2026-01&occupancy_id='.$first->occupancy_id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/owner/invoices?room_id='.$second->occupancy->room_id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/invoices?rental_group_id='.$second->occupancy->rental_group_id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/invoices?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/invoices?payment_status=paid')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/owner/invoices?billing_month=2026-13&per_page=101&payment_status=anything')
            ->assertUnprocessable()->assertJsonValidationErrors(['billing_month', 'per_page', 'payment_status']);
    }

    public function test_tenant_can_only_read_invoices_with_an_explicit_active_grant(): void
    {
        [$own, $user, $representative] = $this->invoice();
        [$other] = $this->invoice();
        $this->grant($own, $user, $representative);
        $this->actingAs($user, 'web');
        $this->getJson('/api/tenant/invoices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/tenant/invoices/'.$own->id)->assertOk()->assertJsonMissingPath('data.access_grants')
            ->assertJsonMissingPath('data.issued_by')->assertJsonMissingPath('data.tenant');
        $this->getJson('/api/tenant/invoices/'.$other->id)->assertNotFound();
        $this->getJson('/api/tenant/invoices/99999')->assertNotFound();
        $this->getJson('/api/tenant/invoices?occupancy_id='.$other->occupancy_id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/owner/invoices/'.$own->id)->assertForbidden();
        $this->putJson('/api/owner/invoices/'.$own->id.'/access', [])->assertForbidden();
        $this->deleteJson('/api/owner/invoices/'.$own->id.'/access')->assertForbidden();
    }

    public function test_representative_membership_does_not_implicitly_grant_an_invoice(): void
    {
        [$invoice, $user] = $this->invoice();
        $this->actingAs($user, 'web');
        $this->getJson('/api/tenant/invoices')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/tenant/invoices/'.$invoice->id)->assertNotFound();
    }

    public function test_owner_replaces_access_and_preserves_history_and_original_representative(): void
    {
        [$invoice, $firstUser, $first] = $this->invoice();
        $secondTenant = Tenant::create(['full_name' => 'Người đại diện mới', 'phone_number' => '0900000002']);
        $secondUser = User::factory()->create(['tenant_id' => $secondTenant->id, 'role' => 'tenant']);
        $stay = TenantStay::create(['occupancy_id' => $invoice->occupancy_id, 'tenant_id' => $secondTenant->id, 'moved_in_at' => '2026-01-01']);
        $first->update(['ended_at' => '2026-02-01']);
        $second = RepresentativeAssignment::create(['occupancy_id' => $invoice->occupancy_id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-02-01']);
        $this->grant($invoice, $firstUser, $first);
        $this->putJson('/api/owner/invoices/'.$invoice->id.'/access', ['representative_assignment_id' => $second->id, 'grant_reason' => 'Bàn giao hóa đơn cũ'])
            ->assertOk()->assertJsonCount(2, 'data.access_grants')->assertJsonPath('data.access_grants.0.is_active', false)
            ->assertJsonPath('data.access_grants.1.user_id', $secondUser->id);
        $this->assertSame($first->id, $invoice->fresh()->representative_assignment_id);
        $this->actingAs($firstUser, 'web')->getJson('/api/tenant/invoices/'.$invoice->id)->assertNotFound();
        $this->actingAs($secondUser, 'web')->getJson('/api/tenant/invoices/'.$invoice->id)->assertOk();
    }

    public function test_historical_grant_survives_departure_and_owner_can_revoke_it_idempotently(): void
    {
        [$invoice, $user, $assignment] = $this->invoice();
        $assignment->update(['ended_at' => '2026-02-01']);
        $assignment->tenantStay->update(['moved_out_at' => '2026-02-01']);
        $assignment->occupancy->update(['ended_at' => '2026-02-01']);
        $this->putJson('/api/owner/invoices/'.$invoice->id.'/access', ['representative_assignment_id' => $assignment->id, 'grant_reason' => 'Xem khoản nợ sau trả phòng'])->assertOk();
        $this->actingAs($user, 'web')->getJson('/api/tenant/invoices/'.$invoice->id)->assertOk();
        $this->actingAs($this->owner, 'web')->deleteJson('/api/owner/invoices/'.$invoice->id.'/access')->assertOk();
        $this->deleteJson('/api/owner/invoices/'.$invoice->id.'/access')->assertOk();
        $this->assertSame(1, InvoiceAccessGrant::count());
        $this->actingAs($user, 'web')->getJson('/api/tenant/invoices/'.$invoice->id)->assertNotFound();
    }

    public function test_access_cannot_cross_occupancies_or_target_an_inactive_or_owner_account(): void
    {
        [$invoice, $user, $assignment] = $this->invoice();
        [, , $other] = $this->invoice();
        $url = '/api/owner/invoices/'.$invoice->id.'/access';
        $this->putJson($url, ['representative_assignment_id' => $other->id, 'grant_reason' => 'Sai đợt thuê'])->assertUnprocessable();
        $user->update(['is_active' => false]);
        $this->putJson($url, ['representative_assignment_id' => $assignment->id, 'grant_reason' => 'Tài khoản khóa'])->assertUnprocessable();
        $user->update(['is_active' => true, 'role' => 'owner']);
        $this->putJson($url, ['representative_assignment_id' => $assignment->id, 'grant_reason' => 'Sai vai trò'])->assertUnprocessable();
        $this->assertSame(0, InvoiceAccessGrant::count());
        $this->putJson($url, ['representative_assignment_id' => $assignment->id, 'grant_reason' => '  ', 'user_id' => $this->owner->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['grant_reason', 'user_id']);
    }

    public function test_inactive_tenant_is_denied_even_when_historical_grant_exists(): void
    {
        [$invoice, $user, $assignment] = $this->invoice();
        $this->grant($invoice, $user, $assignment);
        $user->update(['is_active' => false]);
        $this->actingAs($user, 'web')->getJson('/api/tenant/invoices/'.$invoice->id)->assertUnauthorized();
    }

    public function test_guest_owner_tenant_and_missing_resource_authorization_is_consistent(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/owner/invoices')->assertUnauthorized();
        $this->getJson('/api/tenant/invoices')->assertUnauthorized();
        $this->actingAs($this->owner, 'web')->getJson('/api/tenant/invoices/99999')->assertForbidden();
        $this->getJson('/api/owner/invoices/99999')->assertNotFound();
    }

    public function test_statuses_detect_zero_totals_empty_invoices_and_overpayments(): void
    {
        [$paid] = $this->invoice();
        Payment::create(['invoice_id' => $paid->id, 'amount' => '3000000', 'paid_at' => '2026-02-01',
            'payment_method' => 'bank_transfer', 'idempotency_key' => 'paid', 'recorded_by' => $this->owner->id]);
        $this->getJson('/api/owner/invoices/'.$paid->id)->assertOk()->assertJsonPath('data.payment_status', 'paid');
        Payment::create(['invoice_id' => $paid->id, 'amount' => '1', 'paid_at' => '2026-02-01',
            'payment_method' => 'cash', 'idempotency_key' => 'excess', 'recorded_by' => $this->owner->id]);
        $this->getJson('/api/owner/invoices/'.$paid->id)->assertOk()->assertJsonPath('data.payment_status', 'inconsistent');
        [$empty] = $this->invoice();
        $empty->items()->delete();
        $this->getJson('/api/owner/invoices/'.$empty->id)->assertOk()->assertJsonPath('data.payment_status', 'inconsistent');
        [$zero] = $this->invoice();
        $zero->items()->update(['unit_price' => '0']);
        $this->getJson('/api/owner/invoices/'.$zero->id)->assertOk()->assertJsonPath('data.payment_status', 'paid');
    }

    private function invoice(string $month = '2026-01-01'): array
    {
        $tenant = Tenant::create(['full_name' => 'Người thuê kiểm thử', 'phone_number' => '0900000001']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        $room = Room::create(['room_number' => 'R-'.(Room::count() + 1), 'capacity' => 4, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => 'G-'.(RentalGroup::count() + 1)]);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '3000000']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        $assignment = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $invoice = Invoice::create(['invoice_number' => 'TEST-'.(Invoice::count() + 1), 'occupancy_id' => $occupancy->id, 'billing_month' => $month,
            'representative_assignment_id' => $assignment->id, 'issued_by' => $this->owner->id]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id, 'item_type' => 'rent', 'description' => 'Tiền phòng',
            'unit' => 'tháng', 'quantity' => '1', 'unit_price' => '3000000']);

        return [$invoice, $user, $assignment];
    }

    private function grant(Invoice $invoice, User $user, RepresentativeAssignment $assignment): InvoiceAccessGrant
    {
        return InvoiceAccessGrant::create(['invoice_id' => $invoice->id, 'occupancy_id' => $invoice->occupancy_id,
            'user_id' => $user->id, 'representative_assignment_id' => $assignment->id, 'granted_by' => $this->owner->id, 'grant_reason' => 'Quyền kiểm thử']);
    }
}
