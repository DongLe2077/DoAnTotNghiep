<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class TenantPortalTest extends TestCase
{
    use CreatesBillingSchema;

    private User $account;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(12, 0));
        $this->createBillingSchema();
        $this->tenant = Tenant::create(['full_name' => 'Nguyễn Người Thuê', 'phone_number' => '0900000000', 'identity_number' => 'TEST-SELF']);
        $this->account = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant']);
        $this->actingAs($this->account, 'web');
    }

    public function test_profile_is_always_scoped_to_the_authenticated_account(): void
    {
        $other = Tenant::create(['full_name' => 'Hồ sơ khác', 'phone_number' => '0999999999', 'identity_number' => 'SECRET-OTHER']);
        $this->getJson('/api/tenant/profile?tenant_id='.$other->id.'&user_id=999')->assertOk()->assertExactJson(['data' => [
            'id' => $this->tenant->id, 'full_name' => 'Nguyễn Người Thuê', 'phone_number' => '0900000000', 'identity_number' => 'TEST-SELF',
            'account' => ['id' => $this->account->id, 'username' => $this->account->username, 'role' => 'tenant',
                'tenant_id' => $this->tenant->id, 'is_active' => true],
        ]])->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('SECRET-OTHER')->assertDontSee('password_hash');
        $this->getJson('/api/tenant/profile/'.$other->id)->assertNotFound();
        $this->patchJson('/api/tenant/profile', ['full_name' => 'Ghi đè', 'role' => 'owner'])->assertStatus(405);
        $this->assertSame('Nguyễn Người Thuê', $this->tenant->fresh()->full_name);
    }

    public function test_linked_tenant_without_a_stay_gets_null_current_stay_and_empty_history(): void
    {
        $this->getJson('/api/tenant/current-stay')->assertOk()->assertExactJson(['data' => null]);
        $this->getJson('/api/tenant/stays')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_current_room_shows_snapshotted_services_and_only_own_rep_status(): void
    {
        [$occupancy, $stay] = $this->stay();
        RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $service = Service::create(['service_code' => 'INTERNET', 'name' => 'Internet', 'unit' => 'phòng',
            'billing_method' => 'per_room', 'default_unit_price' => '999', 'is_active' => false]);
        $old = OccupancyService::create(['occupancy_id' => $occupancy->id, 'service_id' => $service->id,
            'effective_from' => '2026-01-01', 'effective_until' => '2026-03-01', 'unit_price' => '100', 'unit' => 'phòng', 'billing_method' => 'per_room']);
        $active = OccupancyService::create(['occupancy_id' => $occupancy->id, 'service_id' => $service->id,
            'effective_from' => '2026-03-01', 'unit_price' => '120', 'unit' => 'phòng', 'billing_method' => 'per_room']);
        $coTenant = Tenant::create(['full_name' => 'PRIVATE-COTENANT', 'phone_number' => 'SECRET-PHONE', 'identity_number' => 'SECRET-ID']);
        TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $coTenant->id, 'moved_in_at' => '2026-01-01']);

        $response = $this->getJson('/api/tenant/current-stay')->assertOk()->assertJsonPath('data.id', $stay->id)
            ->assertJsonPath('data.room.id', $occupancy->room_id)->assertJsonPath('data.monthly_room_rent', '3000000.00')
            ->assertJsonPath('data.is_current_representative', true)->assertJsonPath('data.can_submit_maintenance', true)
            ->assertJsonCount(1, 'data.services')->assertJsonPath('data.services.0.id', $active->id)
            ->assertJsonPath('data.services.0.unit_price', '120.0000')->assertJsonPath('data.services.0.service.name', 'Internet')
            ->assertDontSee('PRIVATE-COTENANT')->assertDontSee('SECRET-PHONE')->assertDontSee('SECRET-ID')
            ->assertJsonMissingPath('data.members')->assertJsonMissingPath('data.representatives')->assertJsonMissingPath('data.account');
        $this->assertNotSame($old->id, $response->json('data.services.0.id'));
    }

    public function test_regular_member_can_view_room_but_cannot_become_rep_by_request_parameters(): void
    {
        [$occupancy] = $this->stay();
        $otherTenant = Tenant::create(['full_name' => 'Đại diện khác', 'phone_number' => '0900000001']);
        $otherStay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $otherTenant->id, 'moved_in_at' => '2026-01-01']);
        RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $otherStay->id, 'started_at' => '2026-01-01']);
        $this->getJson('/api/tenant/current-stay?tenant_id='.$otherTenant->id.'&is_current_representative=true')
            ->assertOk()->assertJsonPath('data.is_current_representative', false)->assertJsonPath('data.can_submit_maintenance', false);
        $this->postJson('/api/tenant/occupancies/'.$occupancy->id.'/maintenance-requests', ['title' => 'Lỗi', 'description' => 'Mô tả'])
            ->assertNotFound();
    }

    public function test_own_history_survives_leaving_and_filters_cannot_reveal_other_stays(): void
    {
        [$old, $oldStay] = $this->stay();
        $oldStay->update(['moved_out_at' => '2026-02-01']);
        $old->update(['ended_at' => '2026-02-01']);
        [$current, $currentStay] = $this->stay('2026-02-01');
        $otherTenant = Tenant::create(['full_name' => 'PRIVATE-HISTORY', 'phone_number' => '0999999999']);
        TenantStay::create(['occupancy_id' => $current->id, 'tenant_id' => $otherTenant->id, 'moved_in_at' => '2026-02-01']);
        $this->getJson('/api/tenant/stays?tenant_id='.$otherTenant->id.'&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $currentStay->id)->assertJsonMissingPath('data.0.services');
        $this->getJson('/api/tenant/stays?page=2&per_page=1')->assertJsonPath('data.0.id', $oldStay->id)
            ->assertJsonPath('data.0.is_active', false)->assertDontSee('PRIVATE-HISTORY');
        $this->getJson('/api/tenant/current-stay')->assertJsonPath('data.occupancy_id', $current->id);
        $currentStay->update(['moved_out_at' => '2026-03-10 12:00:00']);
        $this->getJson('/api/tenant/current-stay')->assertExactJson(['data' => null]);
        $this->getJson('/api/tenant/stays')->assertJsonCount(2, 'data');
    }

    public function test_representative_change_is_reflected_without_granting_invoice_access(): void
    {
        [$occupancy, $stay] = $this->stay();
        $rep = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $invoice = Invoice::create(['invoice_number' => 'PRIVATE-INVOICE', 'occupancy_id' => $occupancy->id,
            'billing_month' => '2026-01-01', 'representative_assignment_id' => $rep->id,
            'issued_by' => User::factory()->create()->id]);
        $this->getJson('/api/tenant/current-stay')->assertJsonPath('data.is_current_representative', true);
        $this->getJson('/api/tenant/invoices')->assertJsonCount(0, 'data');
        $this->getJson('/api/tenant/invoices/'.$invoice->id)->assertNotFound();
        $rep->update(['ended_at' => '2026-03-10 12:00:00']);
        $this->getJson('/api/tenant/current-stay')->assertJsonPath('data.is_current_representative', false);
    }

    public function test_closed_occupancy_never_appears_as_current_even_if_stay_data_is_inconsistent(): void
    {
        [$occupancy] = $this->stay();
        $occupancy->update(['ended_at' => '2026-03-01']);
        $this->getJson('/api/tenant/current-stay')->assertExactJson(['data' => null]);
        $this->getJson('/api/tenant/stays')->assertJsonPath('data.0.is_active', false)->assertJsonPath('data.0.can_submit_maintenance', false);
    }

    public function test_account_without_linked_profile_returns_404_without_other_tenant_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'tenant', 'tenant_id' => null]), 'web');
        foreach (['profile', 'current-stay', 'stays'] as $path) {
            $this->getJson('/api/tenant/'.$path)->assertNotFound()->assertDontSee('TEST-SELF');
        }
    }

    public function test_owner_inactive_tenant_and_guest_cannot_access_tenant_portal(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        foreach (['profile', 'current-stay', 'stays'] as $path) {
            $this->getJson('/api/tenant/'.$path)->assertForbidden();
        }
        $this->account->update(['is_active' => false]);
        $this->actingAs($this->account, 'web');
        $this->getJson('/api/tenant/profile')->assertUnauthorized();
        auth('web')->logout();
        foreach (['profile', 'current-stay', 'stays'] as $path) {
            $this->getJson('/api/tenant/'.$path)->assertUnauthorized();
        }
    }

    public function test_history_pagination_is_validated(): void
    {
        foreach (['page=0', 'per_page=101', 'per_page[]=1'] as $query) {
            $this->getJson('/api/tenant/stays?'.$query)->assertUnprocessable();
        }
    }

    private function stay(string $startedAt = '2026-01-01'): array
    {
        $room = Room::create(['room_number' => 'P-'.(Room::count() + 1), 'capacity' => 3,
            'default_monthly_rent' => '9999999', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => (string) Str::uuid()]);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id,
            'started_at' => $startedAt, 'monthly_rent' => '3000000']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $this->tenant->id, 'moved_in_at' => $startedAt]);

        return [$occupancy, $stay];
    }
}
