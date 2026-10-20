<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class BillingRepresentativeTest extends TestCase
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

    public function test_single_resident_without_login_can_finish_rental_and_receive_invoice_and_payment(): void
    {
        [$occupancy, $stay] = $this->closedOccupancy();
        $usersBefore = User::count();
        $before = $stay->fresh()->getAttributes();
        $this->postJson('/api/owner/occupancies/'.$occupancy->id.'/invoices/preview', ['billing_month' => '2026-01'])
            ->assertOk()->assertJsonPath('data.can_issue', false);
        $this->restore($occupancy, $stay->id)->assertCreated()->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.started_at', '2026-01-01 00:00:00')->assertJsonPath('data.ended_at', '2026-02-01 00:00:00');
        $preview = $this->postJson('/api/owner/occupancies/'.$occupancy->id.'/invoices/preview', ['billing_month' => '2026-01'])
            ->assertOk()->assertJsonPath('data.can_issue', true)->assertJsonPath('data.recipient_user_id', null);
        $invoice = $this->postJson('/api/owner/occupancies/'.$occupancy->id.'/invoices', [
            'billing_month' => '2026-01', 'preview_token' => $preview->json('data.preview_token'),
        ])->assertCreated()->assertJsonPath('data.total_amount', '3000000.00');
        $this->postJson('/api/owner/invoices/'.$invoice->json('data.id').'/payments', [
            'amount' => '3000000', 'paid_at' => now()->format('Y-m-d H:i:s'), 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('invoice.payment_status', 'paid');
        $this->assertSame($usersBefore, User::count());
        $this->assertSame(0, InvoiceAccessGrant::count());
        $this->assertSame($before, $stay->fresh()->getAttributes());
        $this->assertNotNull($occupancy->fresh()->ended_at);
    }

    public function test_repair_starts_at_the_selected_residents_actual_move_in(): void
    {
        [$occupancy, $stay] = $this->closedOccupancy();
        $stay->update(['moved_in_at' => '2026-01-15 10:00:00']);
        $this->restore($occupancy, $stay->id)->assertCreated()->assertJsonPath('data.started_at', '2026-01-15 10:00:00');
        $this->restore($occupancy, $stay->id)->assertConflict();
        $this->assertSame(1, RepresentativeAssignment::count());
    }

    public function test_wrong_occupancy_and_resident_who_left_before_checkout_are_rejected(): void
    {
        [$occupancy, $stay] = $this->closedOccupancy();
        [, $other] = $this->closedOccupancy();
        $this->restore($occupancy, $other->id)->assertUnprocessable()->assertJsonValidationErrors('tenant_stay_id');
        $stay->update(['moved_out_at' => '2026-01-15 00:00:00']);
        $this->restore($occupancy, $stay->id)->assertUnprocessable();
        $this->assertSame(0, RepresentativeAssignment::count());
    }

    public function test_open_occupancy_uses_the_existing_representative_workflow(): void
    {
        [$occupancy, $stay] = $this->closedOccupancy();
        $occupancy->update(['ended_at' => null]);
        $this->restore($occupancy, $stay->id)->assertConflict();
        $this->assertSame(0, RepresentativeAssignment::count());
    }

    public function test_existing_representative_history_and_published_invoice_are_preserved(): void
    {
        [$occupancy, $stay] = $this->closedOccupancy();
        $representative = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id,
            'started_at' => '2026-01-01', 'ended_at' => '2026-02-01']);
        $this->restore($occupancy, $stay->id)->assertConflict();
        $invoice = Invoice::create(['invoice_number' => 'EXISTING-1', 'occupancy_id' => $occupancy->id, 'billing_month' => '2026-01-01',
            'representative_assignment_id' => $representative->id, 'issued_by' => $this->owner->id]);
        $this->restore($occupancy, $stay->id)->assertConflict();
        $this->assertSame($representative->id, $invoice->fresh()->representative_assignment_id);
        $this->assertSame(1, RepresentativeAssignment::count());
    }

    public function test_explicit_fields_and_owner_authorization_are_required(): void
    {
        [$occupancy, $stay] = $this->closedOccupancy();
        $url = '/api/owner/occupancies/'.$occupancy->id.'/billing-representative';
        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('tenant_stay_id');
        $this->postJson($url, ['tenant_stay_id' => $stay->id, 'started_at' => '2020-01-01', 'user_id' => 999])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->postJson('/api/owner/occupancies/999999/billing-representative', [])->assertForbidden();
        auth('web')->logout();
        $this->postJson($url, [])->assertUnauthorized();
        $this->assertSame(0, RepresentativeAssignment::count());
    }

    private function restore(Occupancy $occupancy, int $stayId)
    {
        return $this->postJson('/api/owner/occupancies/'.$occupancy->id.'/billing-representative', ['tenant_stay_id' => $stayId]);
    }

    private function closedOccupancy(): array
    {
        $tag = (string) Str::uuid();
        $room = Room::create(['room_number' => 'REP-'.(Room::count() + 1), 'capacity' => 2, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => $tag]);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id,
            'started_at' => '2026-01-01', 'ended_at' => '2026-02-01', 'monthly_rent' => '3000000']);
        $tenant = Tenant::create(['full_name' => 'Người thuê không có tài khoản', 'phone_number' => '0900000000']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id,
            'moved_in_at' => '2026-01-01', 'moved_out_at' => '2026-02-01']);

        return [$occupancy, $stay];
    }
}
