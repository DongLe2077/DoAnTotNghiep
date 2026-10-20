<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesTenancySchema;
use Tests\TestCase;

class TenancyManagementTest extends TestCase
{
    use CreatesTenancySchema;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTenancySchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
    }

    public function test_owner_can_manage_profiles_without_creating_login_accounts(): void
    {
        $response = $this->postJson('/api/owner/tenants', [
            'full_name' => 'Nguyễn Nam', 'phone_number' => '0900123456', 'identity_number' => '012345678901',
            'role' => 'owner', 'username' => 'ignored',
        ])->assertCreated()->assertJsonPath('data.phone_number', '0900123456')->assertJsonPath('data.account', null);
        $id = $response->json('data.id');
        $this->assertSame(1, User::count());
        $this->patchJson("/api/owner/tenants/{$id}", ['full_name' => 'Nam', 'identity_number' => null])->assertOk();
        $this->getJson('/api/owner/tenants?q=090012')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/owner/tenants/{$id}")->assertOk()->assertJsonPath('data.can_delete', true);
        $this->deleteJson("/api/owner/tenants/{$id}")->assertNoContent();
    }

    public function test_profile_validation_rejects_missing_fields_and_duplicate_identity_numbers(): void
    {
        $this->postJson('/api/owner/tenants', [])->assertUnprocessable()->assertJsonValidationErrors(['full_name', 'phone_number']);
        $this->tenant(['identity_number' => '012345678901']);
        $this->postJson('/api/owner/tenants', ['full_name' => 'Lan', 'phone_number' => '0911111111', 'identity_number' => '012345678901'])
            ->assertUnprocessable()->assertJsonValidationErrors('identity_number');
        $this->postJson('/api/owner/tenants', ['full_name' => ' ', 'phone_number' => 901234567])
            ->assertUnprocessable()->assertJsonValidationErrors(['full_name', 'phone_number']);
    }

    public function test_account_creation_hashes_password_and_forces_the_tenant_role(): void
    {
        $tenant = $this->tenant();
        $this->putJson("/api/owner/tenants/{$tenant->id}/account", [
            'username' => 'tenant_demo', 'password' => 'TenantPass123!', 'password_confirmation' => 'TenantPass123!',
            'role' => 'owner', 'tenant_id' => 999,
        ])->assertOk()->assertJsonPath('data.role', 'tenant')->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonMissingPath('data.password_hash');
        $user = $tenant->user()->firstOrFail();
        $this->assertTrue(Hash::check('TenantPass123!', $user->password_hash));
        $this->putJson("/api/owner/tenants/{$tenant->id}/account", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/owner/tenants/{$tenant->id}")->assertStatus(409);
        $this->putJson("/api/owner/tenants/{$tenant->id}/account", ['password' => 'too-short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_owner_accounts_cannot_be_demoted_through_the_tenant_account_endpoint(): void
    {
        $tenant = $this->tenant();
        $linked = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $this->putJson("/api/owner/tenants/{$tenant->id}/account", ['username' => $linked->username])->assertStatus(409);
        $this->assertSame('owner', $linked->fresh()->role);
    }

    public function test_owner_can_manage_unused_groups_and_used_groups_are_preserved(): void
    {
        $created = $this->postJson('/api/owner/rental-groups', ['group_code' => 'GROUP-ONE'])->assertCreated();
        $id = $created->json('data.id');
        $this->patchJson("/api/owner/rental-groups/{$id}", ['group_code' => 'GROUP-TWO'])->assertOk();
        $this->getJson('/api/owner/rental-groups?q=TWO')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/owner/rental-groups/{$id}")->assertNoContent();
        $occupancy = $this->checkIn([$this->tenant()]);
        $this->deleteJson("/api/owner/rental-groups/{$occupancy->rental_group_id}")->assertStatus(409);
    }

    public function test_single_person_room_can_check_in_and_check_out_without_a_login_account(): void
    {
        $tenant = $this->tenant();
        $room = $this->room(['capacity' => 1]);
        $occupancy = $this->checkIn([$tenant], $room);
        $this->getJson("/api/owner/occupancies/{$occupancy->id}")->assertOk()
            ->assertJsonPath('data.resident_count', 1)->assertJsonCount(1, 'data.members')->assertJsonCount(0, 'data.representatives');
        $this->getJson("/api/owner/rooms/{$room->id}")->assertOk()->assertJsonPath('data.occupancy_status', 'occupied');
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2026-02-01 10:00:00'])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson("/api/owner/rooms/{$room->id}")->assertOk()->assertJsonPath('data.resident_count', 0)->assertJsonPath('data.occupancy_status', 'vacant');
        $this->deleteJson("/api/owner/tenants/{$tenant->id}")->assertStatus(409);
    }

    public function test_check_in_rejects_maintenance_overcapacity_duplicate_members_and_client_rent(): void
    {
        $one = $this->tenant();
        $two = $this->tenant();
        $room = $this->room(['capacity' => 1]);
        $group = $this->group();
        $base = ['rental_group_id' => $group->id, 'room_id' => $room->id, 'started_at' => '2026-01-01 10:00:00', 'tenant_ids' => [$one->id]];
        $this->postJson('/api/owner/occupancies', $base + ['monthly_rent' => '1'])->assertUnprocessable();
        $this->postJson('/api/owner/occupancies', array_merge($base, ['tenant_ids' => [$one->id, $one->id]]))->assertUnprocessable();
        $this->postJson('/api/owner/occupancies', array_merge($base, ['tenant_ids' => [$one->id, $two->id]]))->assertStatus(409);
        $room->update(['operational_status' => 'maintenance']);
        $this->postJson('/api/owner/occupancies', $base)->assertStatus(409);
        $this->assertSame(0, Occupancy::count());
    }

    public function test_room_and_group_cannot_have_two_open_occupancies_even_without_residents(): void
    {
        $room = $this->room();
        $group = $this->group();
        Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '3000000']);
        $tenant = $this->tenant();
        $this->checkInFailure([$tenant], $room, $this->group(), 409);
        $this->checkInFailure([$tenant], $this->room(), $group, 409);
    }

    public function test_a_person_cannot_live_in_two_rooms_or_overlap_finished_residence_history(): void
    {
        $tenant = $this->tenant();
        $first = $this->checkIn([$tenant]);
        $secondRoom = $this->room();
        $secondGroup = $this->group();
        $this->checkInFailure([$tenant], $secondRoom, $secondGroup, 409);
        $this->postJson("/api/owner/occupancies/{$first->id}/checkout", ['ended_at' => '2026-02-01 10:00:00'])->assertOk();
        $this->checkInFailure([$tenant], $secondRoom, $secondGroup, 409, '2026-01-15 10:00:00');
        $this->checkIn([$tenant], $secondRoom, $secondGroup, '2026-02-01 10:00:00');
    }

    public function test_representative_requires_membership_and_an_active_tenant_account_and_failure_rolls_back_check_in(): void
    {
        $tenant = $this->tenant();
        $room = $this->room();
        $group = $this->group();
        $body = ['room_id' => $room->id, 'rental_group_id' => $group->id, 'tenant_ids' => [$tenant->id], 'started_at' => '2026-01-01 10:00:00', 'representative_tenant_id' => $tenant->id];
        $this->postJson('/api/owner/occupancies', $body)->assertUnprocessable()->assertJsonValidationErrors('representative_tenant_id');
        $this->assertSame(0, Occupancy::count());
        $this->assertSame(0, TenantStay::count());
        $this->loginAccount($tenant);
        $this->postJson('/api/owner/occupancies', $body)->assertCreated()->assertJsonCount(1, 'data.representatives');
    }

    public function test_member_addition_enforces_capacity_and_departure_preserves_history(): void
    {
        $one = $this->tenant();
        $two = $this->tenant();
        $three = $this->tenant();
        $occupancy = $this->checkIn([$one], $this->room(['capacity' => 2]));
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/members", ['tenant_ids' => [$two->id], 'moved_in_at' => '2026-01-02 10:00:00'])
            ->assertOk()->assertJsonPath('data.resident_count', 2);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/members", ['tenant_ids' => [$three->id], 'moved_in_at' => '2026-01-03 10:00:00'])->assertStatus(409);
        $stay = $occupancy->tenantStays()->where('tenant_id', $two->id)->firstOrFail();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/members/{$stay->id}/leave", ['moved_out_at' => '2026-01-03 10:00:00'])
            ->assertOk()->assertJsonPath('data.resident_count', 1)->assertJsonCount(2, 'data.members');
        $this->getJson("/api/owner/tenants/{$two->id}/stays")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_last_member_must_use_checkout_instead_of_leaving_an_empty_open_occupancy(): void
    {
        $occupancy = $this->checkIn([$this->tenant()]);
        $stay = $occupancy->tenantStays()->firstOrFail();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/members/{$stay->id}/leave", ['moved_out_at' => '2026-02-01 10:00:00'])->assertStatus(409);
        $this->assertNull($stay->fresh()->moved_out_at);
    }

    public function test_departing_representative_must_be_replaced_by_an_eligible_remaining_member(): void
    {
        $one = $this->tenant();
        $two = $this->tenant();
        $this->loginAccount($one);
        $this->loginAccount($two);
        $occupancy = $this->checkIn([$one, $two], representative: $one);
        $stay = $occupancy->tenantStays()->where('tenant_id', $one->id)->firstOrFail();
        $path = "/api/owner/occupancies/{$occupancy->id}/members/{$stay->id}/leave";
        $this->postJson($path, ['moved_out_at' => '2026-02-01 10:00:00'])->assertUnprocessable();
        $this->postJson($path, ['moved_out_at' => '2026-02-01 10:00:00', 'replacement_tenant_id' => $two->id])->assertOk();
        $this->assertNotNull($stay->fresh()->moved_out_at);
        $this->assertSame(2, RepresentativeAssignment::count());
        $active = RepresentativeAssignment::whereNull('ended_at')->firstOrFail();
        $this->assertSame($two->id, $active->tenantStay->tenant_id);
    }

    public function test_ineligible_representatives_and_disabling_a_current_representative_are_rejected(): void
    {
        $one = $this->tenant();
        $two = $this->tenant();
        $outsider = $this->tenant();
        $this->loginAccount($one);
        $this->loginAccount($outsider);
        $occupancy = $this->checkIn([$one, $two], representative: $one);
        $path = "/api/owner/occupancies/{$occupancy->id}/representative";
        foreach ([$two->id, $outsider->id] as $id) {
            $this->postJson($path, ['tenant_id' => $id, 'started_at' => '2026-02-01 10:00:00'])->assertUnprocessable();
        }
        $this->putJson("/api/owner/tenants/{$one->id}/account", ['is_active' => false])->assertStatus(409);
        $this->loginAccount($two);
        $this->postJson($path, ['tenant_id' => $two->id, 'started_at' => '2026-02-01 10:00:00'])->assertOk();
        $this->putJson("/api/owner/tenants/{$one->id}/account", ['is_active' => false])->assertOk();
    }

    public function test_service_price_changes_create_new_intervals_and_retain_old_snapshots(): void
    {
        $occupancy = $this->checkIn([$this->tenant()]);
        $service = $this->service();
        $path = "/api/owner/occupancies/{$occupancy->id}/services";
        $this->postJson($path, ['service_id' => $service->id, 'effective_from' => '2026-01-01 10:00:00'])->assertCreated();
        $this->postJson($path, ['service_id' => $service->id, 'effective_from' => '2026-02-01 10:00:00', 'unit_price' => '4500.1234'])->assertCreated();
        $periods = $occupancy->occupancyServices()->orderBy('id')->get();
        $this->assertSame('3500.0000', $periods[0]->unit_price);
        $this->assertSame('2026-02-01 10:00:00', $periods[0]->effective_until->format('Y-m-d H:i:s'));
        $this->assertSame('4500.1234', $periods[1]->unit_price);
        $service->update(['is_active' => false]);
        $this->postJson($path, ['service_id' => $service->id, 'effective_from' => '2026-03-01 10:00:00'])->assertStatus(409);
        $this->postJson($path."/{$periods[1]->id}/end", ['effective_until' => '2026-03-01 10:00:00'])->assertOk();
    }

    public function test_meter_and_billed_periods_cannot_be_truncated_by_service_changes_or_checkout(): void
    {
        $tenant = $this->tenant();
        $this->loginAccount($tenant);
        $occupancy = $this->checkIn([$tenant], representative: $tenant);
        $service = $this->service();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/services", ['service_id' => $service->id, 'effective_from' => '2026-01-01 10:00:00'])->assertCreated();
        $period = $occupancy->occupancyServices()->firstOrFail();
        MeterReading::create(['occupancy_service_id' => $period->id, 'period_start' => '2026-01-01 10:00:00', 'period_end' => '2026-02-01 10:00:00', 'previous_reading' => 0, 'current_reading' => 10, 'recorded_by' => $this->owner->id]);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/services/{$period->id}/end", ['effective_until' => '2026-01-15 10:00:00'])->assertUnprocessable();
        $invoice = $this->invoice($occupancy);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id, 'item_type' => 'service', 'description' => 'Service', 'unit' => 'kwh', 'quantity' => 10, 'unit_price' => 3500, 'occupancy_service_id' => $period->id, 'charge_period_start' => '2026-01-01 10:00:00', 'charge_period_end' => '2026-03-01 10:00:00']);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2026-02-15 10:00:00'])->assertUnprocessable();
        $this->assertNull($occupancy->fresh()->ended_at);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2026-03-01 10:00:00'])->assertOk();
    }

    public function test_whole_group_transfer_preserves_rent_prices_members_and_invoice_history(): void
    {
        $one = $this->tenant();
        $two = $this->tenant();
        $user = $this->loginAccount($one);
        $old = $this->checkIn([$one, $two], representative: $one);
        $service = $this->service();
        $this->postJson("/api/owner/occupancies/{$old->id}/services", ['service_id' => $service->id, 'effective_from' => '2026-01-01 10:00:00', 'unit_price' => '3200'])->assertCreated();
        $invoice = $this->invoice($old);
        $grant = InvoiceAccessGrant::create(['invoice_id' => $invoice->id, 'occupancy_id' => $old->id, 'user_id' => $user->id, 'representative_assignment_id' => $invoice->representative_assignment_id, 'granted_by' => $this->owner->id, 'grant_reason' => 'Historical invoice']);
        $destination = $this->room(['default_monthly_rent' => '4000000']);
        $service->update(['default_unit_price' => '5000', 'is_active' => false]);
        $transfer = $this->postJson("/api/owner/occupancies/{$old->id}/transfer", ['room_id' => $destination->id, 'transferred_at' => '2026-02-01 10:00:00'])
            ->assertCreated()->assertJsonPath('data.previous_occupancy_id', $old->id)->assertJsonPath('data.monthly_rent', '3000000.00')
            ->assertJsonPath('data.rental_group_id', $old->rental_group_id)->assertJsonPath('data.resident_count', 2)->assertJsonPath('data.services.0.unit_price', '3200.0000');
        $next = Occupancy::findOrFail($transfer->json('data.id'));
        $this->assertNotNull($old->fresh()->ended_at);
        $this->assertSame(0, $old->tenantStays()->whereNull('moved_out_at')->count());
        $this->assertSame(0, $old->representativeAssignments()->whereNull('ended_at')->count());
        $this->assertSame(0, $old->occupancyServices()->whereNull('effective_until')->count());
        $this->assertSame($old->id, $invoice->fresh()->occupancy_id);
        $this->assertNull($grant->fresh()->revoked_at);
        $this->assertSame(1, DB::table('active_invoice_access')->where('user_id', $user->id)->count());
        $this->assertSame(1, Occupancy::where('rental_group_id', $old->rental_group_id)->whereNull('ended_at')->count());
        $this->getJson("/api/owner/rooms/{$next->room_id}")->assertOk()->assertJsonPath('data.resident_count', 2);
        $this->getJson("/api/owner/tenants/{$one->id}/stays")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_invalid_transfer_rolls_back_every_closed_period_and_new_row(): void
    {
        $one = $this->tenant();
        $this->loginAccount($one);
        $old = $this->checkIn([$one], representative: $one);
        $destination = $this->room();
        $outsider = $this->tenant();
        $this->loginAccount($outsider);
        $this->postJson("/api/owner/occupancies/{$old->id}/transfer", [
            'room_id' => $destination->id, 'transferred_at' => '2026-02-01 10:00:00', 'representative_tenant_id' => $outsider->id,
        ])->assertUnprocessable();
        $this->assertNull($old->fresh()->ended_at);
        $this->assertSame(1, Occupancy::count());
        $this->assertSame(1, $old->tenantStays()->whereNull('moved_out_at')->count());
        $this->assertSame(1, $old->representativeAssignments()->whereNull('ended_at')->count());
    }

    public function test_checkout_closes_every_open_interval_and_closed_history_cannot_be_mutated(): void
    {
        $tenant = $this->tenant();
        $this->loginAccount($tenant);
        $occupancy = $this->checkIn([$tenant], representative: $tenant);
        $service = $this->service();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/services", ['service_id' => $service->id, 'effective_from' => '2026-01-02 10:00:00'])->assertCreated();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2026-02-01 10:00:00'])->assertOk();
        $this->assertSame(0, TenantStay::whereNull('moved_out_at')->count());
        $this->assertSame(0, RepresentativeAssignment::whereNull('ended_at')->count());
        $this->assertSame(0, OccupancyService::whereNull('effective_until')->count());
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2026-03-01 10:00:00'])->assertStatus(409);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/members", ['tenant_ids' => [$this->tenant()->id], 'moved_in_at' => '2026-03-01 10:00:00'])->assertStatus(409);
        $this->patchJson("/api/owner/occupancies/{$occupancy->id}", ['ended_at' => null])->assertStatus(405);
    }

    public function test_zero_duration_or_retroactive_changes_are_rejected_and_partial_updates_roll_back(): void
    {
        $occupancy = $this->checkIn([$this->tenant()]);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/members", ['tenant_ids' => [$this->tenant()->id], 'moved_in_at' => '2026-02-01 10:00:00'])->assertOk();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2026-01-15 10:00:00'])->assertUnprocessable();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2026-02-01 10:00:00'])->assertUnprocessable();
        $this->assertNull($occupancy->fresh()->ended_at);
        $this->assertSame(2, TenantStay::whereNull('moved_out_at')->count());
    }

    public function test_nested_member_and_service_period_ids_must_belong_to_the_parent_occupancy(): void
    {
        $one = $this->checkIn([$this->tenant()]);
        $two = $this->checkIn([$this->tenant()]);
        $stay = $two->tenantStays()->firstOrFail();
        $service = $this->service();
        $this->postJson("/api/owner/occupancies/{$two->id}/services", ['service_id' => $service->id, 'effective_from' => '2026-01-02 10:00:00'])->assertCreated();
        $period = $two->occupancyServices()->firstOrFail();
        // Add a second member so that the leave operation reaches the scoped lookup.
        $this->postJson("/api/owner/occupancies/{$one->id}/members", ['tenant_ids' => [$this->tenant()->id], 'moved_in_at' => '2026-01-02 10:00:00'])->assertOk();
        $this->postJson("/api/owner/occupancies/{$one->id}/members/{$stay->id}/leave", ['moved_out_at' => '2026-02-01 10:00:00'])->assertNotFound();
        $this->postJson("/api/owner/occupancies/{$one->id}/services/{$period->id}/end", ['effective_until' => '2026-02-01 10:00:00'])->assertNotFound();
    }

    public function test_history_and_occupancy_filters_return_only_the_requested_records(): void
    {
        $one = $this->tenant(['full_name' => 'Nam']);
        $two = $this->tenant(['full_name' => 'Lan']);
        $occupancy = $this->checkIn([$one]);
        $this->getJson('/api/owner/tenants?is_residing=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $one->id);
        $this->getJson('/api/owner/tenants?is_residing=0')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $two->id);
        $this->getJson('/api/owner/occupancies?tenant_id='.$one->id)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/owner/occupancies?tenant_id='.$two->id)->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2026-02-01 10:00:00'])->assertOk();
        $this->getJson('/api/owner/occupancies?status=ended&room_id='.$occupancy->room_id)->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_future_dates_invalid_shapes_and_missing_records_return_validation_errors(): void
    {
        $this->postJson('/api/owner/occupancies', [])->assertUnprocessable();
        $this->postJson('/api/owner/occupancies', ['tenant_ids' => 'invalid', 'started_at' => 'tomorrow'])->assertUnprocessable();
        $occupancy = $this->checkIn([$this->tenant()]);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2099-01-01 10:00:00'])->assertUnprocessable();
        $this->getJson('/api/owner/tenants/999')->assertNotFound();
        $this->getJson('/api/owner/occupancies?status=unknown')->assertUnprocessable();
    }

    public function test_guests_and_tenants_cannot_use_any_management_mutation_or_read_other_profiles(): void
    {
        $routes = [
            ['GET', '/tenants'], ['GET', '/tenants/999'], ['POST', '/tenants'], ['PATCH', '/tenants/999'], ['DELETE', '/tenants/999'],
            ['PUT', '/tenants/999/account'], ['GET', '/tenants/999/stays'], ['GET', '/rental-groups'], ['POST', '/rental-groups'], ['PATCH', '/rental-groups/999'], ['DELETE', '/rental-groups/999'],
            ['GET', '/occupancies'], ['POST', '/occupancies'], ['GET', '/occupancies/999'], ['POST', '/occupancies/999/members'],
            ['POST', '/occupancies/999/members/999/leave'], ['POST', '/occupancies/999/representative'], ['POST', '/occupancies/999/services'],
            ['POST', '/occupancies/999/services/999/end'], ['POST', '/occupancies/999/transfer'], ['POST', '/occupancies/999/checkout'],
        ];
        auth('web')->logout();
        foreach ($routes as [$method, $path]) {
            $this->json($method, '/api/owner'.$path)->assertUnauthorized();
        }
        $user = $this->loginAccount($this->tenant());
        $this->actingAs($user, 'web');
        foreach ($routes as [$method, $path]) {
            $this->json($method, '/api/owner'.$path)->assertForbidden();
        }
    }

    public function test_existing_residents_can_remain_during_maintenance_but_new_members_are_blocked(): void
    {
        $room = $this->room();
        $occupancy = $this->checkIn([$this->tenant()], $room);
        $room->update(['operational_status' => 'maintenance']);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/members", ['tenant_ids' => [$this->tenant()->id], 'moved_in_at' => '2026-02-01 10:00:00'])->assertStatus(409);
        $this->assertSame(1, TenantStay::whereNull('moved_out_at')->count());
        $this->assertNull($occupancy->fresh()->ended_at);
    }

    public function test_transfer_requires_a_different_available_room_with_enough_capacity(): void
    {
        $occupancy = $this->checkIn([$this->tenant(), $this->tenant()]);
        $small = $this->room(['capacity' => 1]);
        $maintenance = $this->room(['operational_status' => 'maintenance']);
        $occupied = $this->checkIn([$this->tenant()]);
        foreach ([$occupancy->room_id, $small->id, $maintenance->id, $occupied->room_id] as $roomId) {
            $this->postJson("/api/owner/occupancies/{$occupancy->id}/transfer", ['room_id' => $roomId, 'transferred_at' => '2026-02-01 10:00:00'])->assertStatus(409);
            $this->assertNull($occupancy->fresh()->ended_at);
        }
    }

    public function test_representative_changes_preserve_explicit_historical_invoice_permissions(): void
    {
        $one = $this->tenant();
        $two = $this->tenant();
        $oldUser = $this->loginAccount($one);
        $newUser = $this->loginAccount($two);
        $occupancy = $this->checkIn([$one, $two], representative: $one);
        $invoice = $this->invoice($occupancy);
        $oldAssignment = $invoice->representative_assignment_id;
        $grant = InvoiceAccessGrant::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id, 'user_id' => $oldUser->id, 'representative_assignment_id' => $oldAssignment, 'granted_by' => $this->owner->id, 'grant_reason' => 'Issued invoice']);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/representative", ['tenant_id' => $two->id, 'started_at' => '2026-02-01 10:00:00'])->assertOk();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/checkout", ['ended_at' => '2026-03-01 10:00:00'])->assertOk();
        $this->assertSame($oldAssignment, $invoice->fresh()->representative_assignment_id);
        $this->assertNull($grant->fresh()->revoked_at);
        $this->assertSame(1, DB::table('active_invoice_access')->where('user_id', $oldUser->id)->count());
        $this->assertSame(0, DB::table('active_invoice_access')->where('user_id', $newUser->id)->count());
    }

    public function test_disabled_accounts_cannot_be_selected_as_representatives(): void
    {
        $tenant = $this->tenant();
        $user = $this->loginAccount($tenant);
        $user->update(['is_active' => false]);
        $occupancy = $this->checkIn([$tenant]);
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/representative", ['tenant_id' => $tenant->id, 'started_at' => '2026-02-01 10:00:00'])->assertUnprocessable();
        $this->assertSame(0, RepresentativeAssignment::count());
    }

    public function test_invalid_password_bytes_are_rejected_before_hashing(): void
    {
        $tenant = $this->tenant();
        foreach ([str_repeat('ề', 30), "valid-password\0invalid"] as $password) {
            $this->putJson("/api/owner/tenants/{$tenant->id}/account", ['username' => 'invalid_password_user', 'password' => $password, 'password_confirmation' => $password])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->assertSame(1, User::count());
    }

    public function test_a_password_that_looks_like_a_hash_is_still_treated_as_a_plain_password(): void
    {
        $tenant = $this->tenant();
        $password = Hash::make('a-different-password');
        $this->putJson("/api/owner/tenants/{$tenant->id}/account", [
            'username' => 'hash_shaped_password', 'password' => $password, 'password_confirmation' => $password,
        ])->assertOk();
        $stored = $tenant->user()->firstOrFail()->password_hash;
        $this->assertTrue(Hash::check($password, $stored));
        $this->assertFalse(Hash::check('a-different-password', $stored));
    }

    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::create(array_merge(['full_name' => fake()->name(), 'phone_number' => '0900123456'], $attributes));
    }

    private function room(array $attributes = []): Room
    {
        return Room::create(array_merge(['room_number' => 'R-'.fake()->unique()->numerify('#####'), 'capacity' => 4, 'default_monthly_rent' => '3000000', 'operational_status' => 'available'], $attributes));
    }

    private function group(): RentalGroup
    {
        return RentalGroup::create(['group_code' => 'G-'.fake()->unique()->numerify('#####')]);
    }

    private function loginAccount(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
    }

    private function checkIn(array $tenants, ?Room $room = null, ?RentalGroup $group = null, string $at = '2026-01-01 10:00:00', ?Tenant $representative = null): Occupancy
    {
        $body = ['room_id' => ($room ?? $this->room())->id, 'rental_group_id' => ($group ?? $this->group())->id, 'tenant_ids' => array_map(fn ($tenant) => $tenant->id, $tenants), 'started_at' => $at];
        if ($representative !== null) {
            $body['representative_tenant_id'] = $representative->id;
        }
        $response = $this->postJson('/api/owner/occupancies', $body)->assertCreated();

        return Occupancy::findOrFail($response->json('data.id'));
    }

    private function checkInFailure(array $tenants, Room $room, RentalGroup $group, int $status, string $at = '2026-01-02 10:00:00'): void
    {
        $this->postJson('/api/owner/occupancies', ['room_id' => $room->id, 'rental_group_id' => $group->id, 'tenant_ids' => array_map(fn ($tenant) => $tenant->id, $tenants), 'started_at' => $at])->assertStatus($status);
    }

    private function service(): Service
    {
        return Service::create(['service_code' => 'S-'.fake()->unique()->numerify('#####'), 'name' => 'Điện', 'unit' => 'kwh', 'billing_method' => 'metered', 'default_unit_price' => '3500', 'is_active' => true]);
    }

    private function invoice(Occupancy $occupancy): Invoice
    {
        return Invoice::forceCreate([
            'invoice_number' => 'INV-'.fake()->unique()->numerify('#####'), 'occupancy_id' => $occupancy->id, 'billing_month' => '2026-01-01',
            'representative_assignment_id' => $occupancy->representativeAssignments()->firstOrFail()->id,
            'issued_by' => $this->owner->id, 'issued_at' => '2026-01-15 10:00:00',
        ]);
    }
}
