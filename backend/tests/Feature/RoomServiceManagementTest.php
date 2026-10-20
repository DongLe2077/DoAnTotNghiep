<?php

namespace Tests\Feature;

use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\RentalGroup;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Tests\Concerns\CreatesCatalogSchema;
use Tests\TestCase;

class RoomServiceManagementTest extends TestCase
{
    use CreatesCatalogSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCatalogSchema();
    }

    public function test_owner_can_create_read_update_and_delete_an_unused_room(): void
    {
        $this->owner();
        $created = $this->postJson('/api/owner/rooms', [
            'room_number' => 'A101', 'capacity' => 4, 'default_monthly_rent' => '3000000.00',
        ])->assertCreated()->assertJsonPath('data.occupancy_status', 'vacant')
            ->assertJsonPath('data.operational_status', 'available')->assertJsonPath('data.resident_count', 0)
            ->assertJsonPath('data.default_monthly_rent', '3000000.00')->assertJsonPath('data.can_delete', true);
        $id = $created->json('data.id');

        $this->getJson("/api/owner/rooms/{$id}")->assertOk()->assertJsonPath('data.room_number', 'A101');
        $this->patchJson("/api/owner/rooms/{$id}", ['room_number' => 'A102', 'capacity' => 3, 'operational_status' => 'maintenance'])
            ->assertOk()->assertJsonPath('data.room_number', 'A102')->assertJsonPath('data.occupancy_status', 'maintenance');
        $this->deleteJson("/api/owner/rooms/{$id}")->assertNoContent();
        $this->getJson("/api/owner/rooms/{$id}")->assertNotFound();
    }

    public function test_first_room_requires_a_rate_and_later_rooms_inherit_the_common_rate(): void
    {
        $this->owner();
        $this->postJson('/api/owner/rooms', ['room_number' => 'A101', 'capacity' => 4])
            ->assertUnprocessable()->assertJsonValidationErrors('default_monthly_rent');
        $this->room();
        $this->postJson('/api/owner/rooms', ['room_number' => 'A102', 'capacity' => 4])
            ->assertCreated()->assertJsonPath('data.default_monthly_rent', '3000000.00');
        $this->postJson('/api/owner/rooms', ['room_number' => 'A103', 'capacity' => 4, 'default_monthly_rent' => '4000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('default_monthly_rent');
    }

    public function test_room_rate_cannot_be_changed_individually(): void
    {
        $this->owner();
        $room = $this->room();
        $this->patchJson("/api/owner/rooms/{$room->id}", ['default_monthly_rent' => '4000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('default_monthly_rent');
        $this->assertSame('3000000.00', $room->fresh()->default_monthly_rent);
        $this->putJson("/api/owner/rooms/{$room->id}", ['default_monthly_rent' => '03000000.0'])
            ->assertOk()->assertJsonPath('data.default_monthly_rent', '3000000.00');
    }

    public function test_common_rate_changes_all_room_defaults_and_preserves_the_agreed_rent(): void
    {
        $this->owner();
        $room = $this->room();
        $this->room(['room_number' => 'A102']);
        $occupancy = $this->occupancy($room);

        $this->getJson('/api/owner/rooms/rental-rate')->assertOk()
            ->assertJsonPath('data.default_monthly_rent', '3000000.00')->assertJsonPath('data.room_count', 2);
        $this->putJson('/api/owner/rooms/rental-rate', ['default_monthly_rent' => '3500000.50'])
            ->assertOk()->assertJsonPath('data.default_monthly_rent', '3500000.50')->assertJsonPath('data.is_uniform', true);

        $this->assertSame(['3500000.50'], Room::all()->pluck('default_monthly_rent')->unique()->values()->all());
        $this->assertSame('3000000.00', $occupancy->fresh()->monthly_rent);
    }

    public function test_inconsistent_legacy_rates_can_be_repaired_by_the_common_rate_endpoint(): void
    {
        $this->owner();
        $this->room();
        $this->room(['room_number' => 'A102', 'default_monthly_rent' => '4000000']);
        $this->getJson('/api/owner/rooms/rental-rate')->assertOk()->assertJsonPath('data.is_uniform', false);
        $this->postJson('/api/owner/rooms', ['room_number' => 'A103', 'capacity' => 2])->assertStatus(409);
        $this->putJson('/api/owner/rooms/rental-rate', ['default_monthly_rent' => '3200000'])
            ->assertOk()->assertJsonPath('data.is_uniform', true);
    }

    public function test_empty_catalog_reports_no_rate_and_rejects_changing_a_nonexistent_common_rate(): void
    {
        $this->owner();
        $this->getJson('/api/owner/rooms/rental-rate')->assertOk()
            ->assertJsonPath('data.default_monthly_rent', null)->assertJsonPath('data.room_count', 0);
        $this->putJson('/api/owner/rooms/rental-rate', ['default_monthly_rent' => '100'])->assertStatus(409);
    }

    public function test_room_lists_count_current_residents_and_filter_derived_status_with_pagination(): void
    {
        $this->owner();
        $occupied = $this->room();
        $active = $this->occupancy($occupied);
        $this->resident($active);
        $this->resident($active);
        $this->resident($active, ['moved_out_at' => '2026-02-01']);
        $vacant = $this->room(['room_number' => 'A102']);
        $old = $this->occupancy($vacant, ['ended_at' => '2026-03-01']);
        $this->resident($old);
        $maintenance = $this->room(['room_number' => 'A103', 'operational_status' => 'maintenance']);
        $this->resident($this->occupancy($maintenance));

        $this->getJson('/api/owner/rooms?occupancy_status=occupied')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $occupied->id)->assertJsonPath('data.0.resident_count', 2);
        $this->getJson('/api/owner/rooms?occupancy_status=vacant')->assertOk()->assertJsonPath('data.0.id', $vacant->id);
        $this->getJson('/api/owner/rooms?operational_status=maintenance&q=A103')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.resident_count', 1)->assertJsonPath('data.0.occupancy_status', 'maintenance');
        $this->getJson('/api/owner/rooms?per_page=1&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.id', $vacant->id);
    }

    public function test_capacity_cannot_drop_below_the_current_number_of_residents(): void
    {
        $this->owner();
        $room = $this->room();
        $occupancy = $this->occupancy($room);
        $this->resident($occupancy);
        $this->resident($occupancy);
        $this->patchJson("/api/owner/rooms/{$room->id}", ['capacity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('capacity');
        $this->patchJson("/api/owner/rooms/{$room->id}", ['capacity' => 2])->assertOk()->assertJsonPath('data.capacity', 2);
    }

    public function test_room_with_finished_rental_history_cannot_be_deleted(): void
    {
        $this->owner();
        $room = $this->room();
        $this->occupancy($room, ['ended_at' => '2026-03-01']);
        $this->getJson("/api/owner/rooms/{$room->id}")->assertOk()->assertJsonPath('data.can_delete', false);
        $this->deleteJson("/api/owner/rooms/{$room->id}")->assertStatus(409);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_room_validation_rejects_invalid_status_capacity_precision_and_duplicate_numbers(): void
    {
        $this->owner();
        $room = $this->room();
        foreach ([['room_number' => 'a101'], ['room_number' => ' '], ['room_number' => str_repeat('X', 31)], ['capacity' => 0], ['capacity' => 65536], ['capacity' => 1.5], ['operational_status' => 'occupied'], ['default_monthly_rent' => '-1'], ['default_monthly_rent' => '1.001'], ['default_monthly_rent' => '10000000000000000'], ['default_monthly_rent' => []]] as $invalid) {
            $payload = array_merge(['room_number' => 'NEW', 'capacity' => 4], $invalid);
            $this->postJson('/api/owner/rooms', $payload)->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->patchJson("/api/owner/rooms/{$room->id}", ['room_number' => 'A101'])->assertOk();
    }

    public function test_room_derived_fields_and_ids_cannot_be_overwritten_by_the_client(): void
    {
        $this->owner();
        $this->postJson('/api/owner/rooms', [
            'id' => 900, 'room_number' => 'A101', 'capacity' => 4, 'default_monthly_rent' => '0',
            'resident_count' => 999, 'occupancy_status' => 'occupied', 'created_at' => '1990-01-01',
        ])->assertCreated()->assertJsonPath('data.resident_count', 0)->assertJsonPath('data.occupancy_status', 'vacant')
            ->assertJsonPath('data.default_monthly_rent', '0.00');
        $this->assertDatabaseMissing('rooms', ['id' => 900]);
    }

    public function test_owner_can_create_update_and_delete_an_unused_service(): void
    {
        $this->owner();
        $created = $this->postJson('/api/owner/services', $this->servicePayload(['default_unit_price' => '3500.1234']))
            ->assertCreated()->assertJsonPath('data.default_unit_price', '3500.1234')
            ->assertJsonPath('data.is_active', true)->assertJsonPath('data.can_delete', true);
        $id = $created->json('data.id');
        $this->getJson("/api/owner/services/{$id}")->assertOk()->assertJsonPath('data.service_code', 'ELECTRIC');
        $this->patchJson("/api/owner/services/{$id}", ['name' => 'Electricity updated', 'default_unit_price' => '4000', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.default_unit_price', '4000.0000')->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/owner/services/{$id}")->assertNoContent();
        $this->getJson("/api/owner/services/{$id}")->assertNotFound();
    }

    public function test_changing_service_defaults_preserves_both_current_and_historical_snapshots(): void
    {
        $this->owner();
        $service = Service::create($this->servicePayload());
        $occupancy = $this->occupancy($this->room());
        foreach ([null, '2026-02-01'] as $endedAt) {
            OccupancyService::create([
                'occupancy_id' => $occupancy->id, 'service_id' => $service->id,
                'effective_from' => '2026-01-01', 'effective_until' => $endedAt,
                'unit_price' => '3200.0000', 'billing_method' => 'metered', 'unit' => 'kwh',
            ]);
        }

        $this->patchJson("/api/owner/services/{$service->id}", [
            'unit' => 'room_month', 'billing_method' => 'per_room', 'default_unit_price' => '5000', 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.can_delete', false);
        foreach (OccupancyService::all() as $snapshot) {
            $this->assertSame('3200.0000', $snapshot->unit_price);
            $this->assertSame('metered', $snapshot->billing_method);
            $this->assertSame('kwh', $snapshot->unit);
        }
        $this->assertSame(1, OccupancyService::whereNull('effective_until')->count());
        $this->deleteJson("/api/owner/services/{$service->id}")->assertStatus(409);
    }

    public function test_a_service_used_only_in_finished_history_still_cannot_be_deleted(): void
    {
        $this->owner();
        $service = Service::create($this->servicePayload());
        OccupancyService::create([
            'occupancy_id' => $this->occupancy($this->room())->id, 'service_id' => $service->id,
            'effective_from' => '2026-01-01', 'effective_until' => '2026-02-01',
            'unit_price' => '3200', 'billing_method' => 'metered', 'unit' => 'kwh',
        ]);
        $this->deleteJson("/api/owner/services/{$service->id}")->assertStatus(409);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_service_search_filters_are_grouped_and_support_inactive_records(): void
    {
        $this->owner();
        Service::create($this->servicePayload());
        $inactive = Service::create($this->servicePayload(['service_code' => 'WATER', 'name' => 'Water', 'is_active' => false]));
        Service::create($this->servicePayload(['service_code' => 'ROOM', 'name' => 'Water fixed fee', 'billing_method' => 'per_room']));
        $this->getJson('/api/owner/services?q=Water&is_active=0&billing_method=metered')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inactive->id);
        $this->getJson('/api/owner/services?q=WATER&per_page=1')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_service_validation_rejects_invalid_methods_duplicates_and_decimal_overflow(): void
    {
        $this->owner();
        Service::create($this->servicePayload());
        foreach ([['service_code' => 'electric'], ['service_code' => ' '], ['name' => ''], ['unit' => ' '], ['billing_method' => 'per_person'], ['default_unit_price' => '-1'], ['default_unit_price' => '1.00001'], ['default_unit_price' => '100000000000000'], ['default_unit_price' => []], ['is_active' => 'yes']] as $invalid) {
            $this->postJson('/api/owner/services', $this->servicePayload(array_merge(['service_code' => 'NEW'], $invalid)))
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->postJson('/api/owner/services', $this->servicePayload(['service_code' => 'FREE', 'default_unit_price' => '0']))
            ->assertCreated()->assertJsonPath('data.default_unit_price', '0.0000');
    }

    public function test_all_catalog_routes_reject_guests_and_tenants_including_missing_records(): void
    {
        $routes = [
            ['GET', '/rooms'], ['POST', '/rooms'], ['GET', '/rooms/999'], ['PATCH', '/rooms/999'], ['DELETE', '/rooms/999'],
            ['GET', '/rooms/rental-rate'], ['PUT', '/rooms/rental-rate'],
            ['GET', '/services'], ['POST', '/services'], ['GET', '/services/999'], ['PUT', '/services/999'], ['DELETE', '/services/999'],
        ];
        foreach ($routes as [$method, $path]) {
            $this->json($method, '/api/owner'.$path)->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create(['role' => 'tenant', 'tenant_id' => 12]), 'web');
        foreach ($routes as [$method, $path]) {
            $this->json($method, '/api/owner'.$path)->assertForbidden();
        }
    }

    public function test_disabled_owner_has_no_catalog_access(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => false]), 'web');
        $this->getJson('/api/owner/rooms')->assertUnauthorized();
    }

    public function test_invalid_filters_and_unknown_record_ids_are_handled_without_server_errors(): void
    {
        $this->owner();
        foreach (['/rooms?per_page=101', '/rooms?page=0', '/rooms?occupancy_status=wrong', '/rooms?q[]=x', '/services?is_active=yes', '/services?billing_method=wrong'] as $path) {
            $this->getJson('/api/owner'.$path)->assertUnprocessable();
        }
        $this->getJson('/api/owner/rooms/999')->assertNotFound();
        $this->patchJson('/api/owner/services/999', [])->assertNotFound();
    }

    private function owner(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
    }

    private function room(array $attributes = []): Room
    {
        return Room::create(array_merge(['room_number' => 'A101', 'capacity' => 4, 'default_monthly_rent' => '3000000', 'operational_status' => 'available'], $attributes));
    }

    private function occupancy(Room $room, array $attributes = []): Occupancy
    {
        $group = RentalGroup::create(['group_code' => 'GROUP-'.fake()->unique()->uuid()]);

        return Occupancy::create(array_merge([
            'rental_group_id' => $group->id, 'room_id' => $room->id, 'started_at' => '2026-01-01', 'monthly_rent' => '3000000',
        ], $attributes));
    }

    private function resident(Occupancy $occupancy, array $attributes = []): TenantStay
    {
        $tenant = Tenant::create(['full_name' => fake()->name(), 'phone_number' => '0900000000']);

        return TenantStay::create(array_merge(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01'], $attributes));
    }

    private function servicePayload(array $attributes = []): array
    {
        return array_merge(['service_code' => 'ELECTRIC', 'name' => 'Electricity', 'unit' => 'kwh', 'billing_method' => 'metered', 'default_unit_price' => '3500'], $attributes);
    }
}
