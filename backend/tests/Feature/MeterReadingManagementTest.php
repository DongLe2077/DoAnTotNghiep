<?php

namespace Tests\Feature;

use App\Models\Invoice;
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
use Tests\Concerns\CreatesTenancySchema;
use Tests\TestCase;

class MeterReadingManagementTest extends TestCase
{
    use CreatesTenancySchema;

    private User $owner;

    private Occupancy $occupancy;

    private OccupancyService $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTenancySchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
        $this->occupancy = $this->occupancy();
        $this->period = $this->period($this->occupancy);
    }

    public function test_owner_records_exact_decimal_consumption_and_snapshot_metadata(): void
    {
        $this->postJson($this->url(), $this->payload())->assertCreated()
            ->assertJsonPath('data.previous_reading', '100.125')
            ->assertJsonPath('data.current_reading', '140.250')
            ->assertJsonPath('data.consumption', '40.125')
            ->assertJsonPath('data.recorded_by', $this->owner->id)
            ->assertJsonPath('data.billing_month', '2026-01')
            ->assertJsonPath('data.is_billed', false)
            ->assertJsonPath('data.occupancy_service.unit_price', '3500.0000');
        $this->assertSame(1, MeterReading::count());
    }

    public function test_zero_consumption_is_valid_and_maximum_decimal_precision_is_supported(): void
    {
        $this->postJson($this->url(), $this->payload([
            'previous_reading' => '999999999999.999', 'current_reading' => '999999999999.999',
        ]))->assertCreated()->assertJsonPath('data.consumption', '0.000');
    }

    public function test_negative_reverse_exponent_and_overprecision_readings_are_rejected(): void
    {
        foreach (['-1', '100.0001', '1000000000000', '1e3', 'not-a-number', [], true] as $invalid) {
            $this->postJson($this->url(), $this->payload(['current_reading' => $invalid]))
                ->assertUnprocessable()->assertJsonValidationErrors('current_reading');
        }
        $this->postJson($this->url(), $this->payload(['current_reading' => '100.124']))
            ->assertUnprocessable()->assertJsonValidationErrors('current_reading');
        $this->assertSame(0, MeterReading::count());
    }

    public function test_client_cannot_set_actor_timestamp_or_generated_consumption(): void
    {
        foreach (['recorded_by' => $this->owner->id, 'recorded_at' => '2026-01-01 00:00:00', 'consumption' => '999'] as $field => $value) {
            $this->postJson($this->url(), $this->payload([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(0, MeterReading::count());
    }

    public function test_non_metered_service_and_foreign_occupancy_service_cannot_be_recorded(): void
    {
        $fixed = $this->period($this->occupancy, ['billing_method' => 'per_room']);
        $this->postJson($this->url(), $this->payload(['occupancy_service_id' => $fixed->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('occupancy_service_id');
        $other = $this->period($this->occupancy());
        $this->postJson($this->url(), $this->payload(['occupancy_service_id' => $other->id]))->assertNotFound();
    }

    public function test_period_must_have_strict_timestamp_order_and_not_be_future(): void
    {
        foreach ([
            ['period_start' => '2026-01-01'],
            ['period_end' => '2026-01-01 00:00:00'],
            ['period_end' => '2026-02-30 00:00:00'],
            ['period_start' => '2099-01-01 00:00:00', 'period_end' => '2099-02-01 00:00:00'],
        ] as $change) {
            $this->postJson($this->url(), $this->payload($change))->assertUnprocessable();
        }
        $this->assertSame(0, MeterReading::count());
    }

    public function test_period_stays_inside_service_and_occupancy_intervals(): void
    {
        $this->period->update(['effective_from' => '2026-01-05 00:00:00', 'effective_until' => '2026-01-25 00:00:00']);
        $this->postJson($this->url(), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('period_start');
        $this->postJson($this->url(), $this->payload(['period_start' => '2026-01-05 00:00:00']))
            ->assertUnprocessable()->assertJsonValidationErrors('period_end');
        $this->occupancy->update(['ended_at' => '2026-01-20 00:00:00']);
        $this->postJson($this->url(), $this->payload(['period_start' => '2026-01-05 00:00:00', 'period_end' => '2026-01-25 00:00:00']))
            ->assertUnprocessable()->assertJsonValidationErrors('period_end');
        $this->postJson($this->url(), $this->payload(['period_start' => '2026-01-05 00:00:00', 'period_end' => '2026-01-20 00:00:00']))->assertCreated();
    }

    public function test_month_boundary_is_allowed_but_crossing_into_next_month_is_rejected(): void
    {
        $this->postJson($this->url(), $this->payload(['period_end' => '2026-02-01 00:00:01']))
            ->assertUnprocessable()->assertJsonValidationErrors('period_end');
        $this->postJson($this->url(), $this->payload())->assertCreated();
    }

    public function test_initial_reading_may_start_late_and_subsequent_readings_must_be_contiguous(): void
    {
        $this->postJson($this->url(), $this->payload(['period_start' => '2026-01-10 00:00:00', 'period_end' => '2026-01-20 00:00:00']))->assertCreated();
        $base = ['period_start' => '2026-01-20 00:00:00', 'period_end' => '2026-02-01 00:00:00', 'previous_reading' => '140.250', 'current_reading' => '150.500'];
        $this->postJson($this->url(), $this->payload(array_merge($base, ['period_start' => '2026-01-19 00:00:00'])))
            ->assertUnprocessable()->assertJsonValidationErrors('period_start');
        $this->postJson($this->url(), $this->payload(array_merge($base, ['period_start' => '2026-01-21 00:00:00'])))
            ->assertUnprocessable()->assertJsonValidationErrors('period_start');
        $this->postJson($this->url(), $this->payload(array_merge($base, ['previous_reading' => '140.251'])))
            ->assertUnprocessable()->assertJsonValidationErrors('previous_reading');
        $this->postJson($this->url(), $this->payload($base))->assertCreated()->assertJsonPath('data.consumption', '10.250');
    }

    public function test_duplicate_period_is_rejected_and_does_not_change_history(): void
    {
        $this->postJson($this->url(), $this->payload())->assertCreated();
        $this->postJson($this->url(), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('period_start');
        $this->assertSame(1, MeterReading::count());
    }

    public function test_only_latest_reading_can_be_corrected_or_deleted(): void
    {
        $first = $this->createReading(['period_end' => '2026-01-15 00:00:00']);
        $second = $this->createReading(['period_start' => '2026-01-15 00:00:00', 'previous_reading' => '140.250', 'current_reading' => '180.000']);
        $this->putJson($this->url($first), $this->payload())->assertStatus(409);
        $this->deleteJson($this->url($first))->assertStatus(409);
        $this->putJson($this->url($second), $this->payload([
            'period_start' => '2026-01-15 00:00:00', 'previous_reading' => '140.250', 'current_reading' => '190.000',
        ]))->assertOk()->assertJsonPath('data.consumption', '49.750')->assertJsonPath('data.recorded_by', $this->owner->id);
        $this->deleteJson($this->url($second))->assertNoContent();
        $this->deleteJson($this->url($first))->assertNoContent();
        $this->assertSame(0, MeterReading::count());
    }

    public function test_correcting_latest_reading_cannot_break_prior_baseline_or_change_service(): void
    {
        $this->createReading(['period_end' => '2026-01-15 00:00:00']);
        $second = $this->createReading(['period_start' => '2026-01-15 00:00:00', 'previous_reading' => '140.250', 'current_reading' => '180.000']);
        $base = ['period_start' => '2026-01-15 00:00:00', 'previous_reading' => '140.249', 'current_reading' => '180.000'];
        $this->putJson($this->url($second), $this->payload($base))->assertUnprocessable()->assertJsonValidationErrors('previous_reading');
        $other = $this->period($this->occupancy);
        $this->putJson($this->url($second), $this->payload(['occupancy_service_id' => $other->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('occupancy_service_id');
        $this->assertSame('180.000', $second->fresh()->current_reading);
    }

    public function test_reading_from_another_occupancy_is_hidden_from_update_and_delete(): void
    {
        $reading = $this->createReading();
        $other = $this->occupancy();
        $this->putJson("/api/owner/occupancies/{$other->id}/meter-readings/{$reading->id}", $this->payload())->assertNotFound();
        $this->deleteJson("/api/owner/occupancies/{$other->id}/meter-readings/{$reading->id}")->assertNotFound();
        $this->assertSame(1, MeterReading::count());
    }

    public function test_invoice_freezes_all_reading_mutations_for_the_billing_month(): void
    {
        $reading = $this->createReading(['period_end' => '2026-01-15 00:00:00']);
        $this->invoice('2026-01-01');
        $this->putJson($this->url($reading), $this->payload())->assertStatus(409);
        $this->deleteJson($this->url($reading))->assertStatus(409);
        $this->postJson($this->url(), $this->payload([
            'period_start' => '2026-01-15 00:00:00', 'previous_reading' => '140.250', 'current_reading' => '180.000',
        ]))->assertStatus(409);
        $this->assertSame(1, MeterReading::count());
    }

    public function test_reading_cannot_be_moved_into_an_invoiced_month(): void
    {
        $reading = $this->createReading();
        $this->invoice('2026-02-01');
        $this->putJson($this->url($reading), $this->payload(['period_start' => '2026-02-01 00:00:00', 'period_end' => '2026-03-01 00:00:00']))->assertStatus(409);
        $this->assertSame('2026-01-01', $reading->fresh()->period_start->format('Y-m-d'));
    }

    public function test_billed_reading_is_immutable_even_if_invoice_has_another_billing_month(): void
    {
        $reading = $this->createReading();
        $invoice = $this->invoice('2026-02-01');
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'occupancy_id' => $this->occupancy->id, 'item_type' => 'service',
            'description' => 'Điện', 'unit' => 'kWh', 'quantity' => '40.125', 'unit_price' => '3500',
            'occupancy_service_id' => $this->period->id, 'meter_reading_id' => $reading->id,
        ]);
        $this->putJson($this->url($reading), $this->payload())->assertStatus(409);
        $this->deleteJson($this->url($reading))->assertStatus(409);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.0.is_billed', true)->assertJsonPath('data.0.invoice_id', $invoice->id);
    }

    public function test_same_meter_carries_reading_forward_when_price_version_changes(): void
    {
        $this->period->update(['effective_until' => '2026-01-15 00:00:00']);
        $first = $this->createReading(['period_end' => '2026-01-15 00:00:00']);
        $next = $this->period($this->occupancy, ['service_id' => $this->period->service_id, 'effective_from' => '2026-01-15 00:00:00', 'unit_price' => '4000']);
        $base = ['occupancy_service_id' => $next->id, 'period_start' => '2026-01-15 00:00:00', 'previous_reading' => '140.250', 'current_reading' => '180.000'];
        $this->postJson($this->url(), $this->payload(array_merge($base, ['previous_reading' => '0'])))
            ->assertUnprocessable()->assertJsonValidationErrors('previous_reading');
        $this->postJson($this->url(), $this->payload($base))->assertCreated();
        $this->deleteJson($this->url($first))->assertStatus(409);
    }

    public function test_another_room_occupancy_starts_with_its_own_meter_baseline(): void
    {
        $this->createReading();
        $other = $this->occupancy();
        $period = $this->period($other, ['service_id' => $this->period->service_id]);
        $this->postJson("/api/owner/occupancies/{$other->id}/meter-readings", $this->payload([
            'occupancy_service_id' => $period->id, 'previous_reading' => '10', 'current_reading' => '20',
        ]))->assertCreated()->assertJsonPath('data.consumption', '10.000');
    }

    public function test_listing_is_paginated_filtered_and_scoped_to_the_selected_occupancy(): void
    {
        $this->createReading();
        $this->createReading(['period_start' => '2026-02-01 00:00:00', 'period_end' => '2026-03-01 00:00:00', 'previous_reading' => '140.250', 'current_reading' => '180']);
        $other = $this->period($this->occupancy());
        MeterReading::create($this->payload(['occupancy_service_id' => $other->id]) + ['recorded_by' => $this->owner->id]);
        $this->getJson($this->url().'?billing_month=2026-01&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->getJson($this->url().'?occupancy_service_id='.$other->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->url().'?per_page=101&billing_month=2026-13')->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'billing_month']);
    }

    public function test_guests_tenants_and_inactive_owners_cannot_operate_meter_readings(): void
    {
        $url = $this->url();
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
        $this->postJson($url, $this->payload())->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, $this->payload())->assertForbidden();
        $this->putJson($url.'/999999', $this->payload())->assertForbidden();
        $this->deleteJson($url.'/999999')->assertForbidden();
        $this->owner->update(['is_active' => false]);
        $this->actingAs($this->owner, 'web');
        $this->postJson($url, $this->payload())->assertUnauthorized();
        $this->assertSame(0, MeterReading::count());
    }

    private function occupancy(): Occupancy
    {
        $room = Room::create(['room_number' => 'M-'.fake()->unique()->numerify('#####'), 'capacity' => 4, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => 'M-'.fake()->unique()->numerify('#####')]);

        return Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01 00:00:00', 'monthly_rent' => '3000000']);
    }

    private function period(Occupancy $occupancy, array $attributes = []): OccupancyService
    {
        $service = isset($attributes['service_id']) ? Service::findOrFail($attributes['service_id']) : Service::create([
            'service_code' => 'M-'.fake()->unique()->numerify('#####'), 'name' => 'Điện', 'unit' => 'kWh',
            'billing_method' => 'metered', 'default_unit_price' => '3500', 'is_active' => true,
        ]);

        return OccupancyService::create(array_merge([
            'occupancy_id' => $occupancy->id, 'service_id' => $service->id, 'effective_from' => '2026-01-01 00:00:00',
            'unit_price' => '3500', 'billing_method' => 'metered', 'unit' => 'kWh',
        ], $attributes));
    }

    private function payload(array $changes = []): array
    {
        return array_merge([
            'occupancy_service_id' => $this->period->id,
            'period_start' => '2026-01-01 00:00:00', 'period_end' => '2026-02-01 00:00:00',
            'previous_reading' => '100.125', 'current_reading' => '140.250',
        ], $changes);
    }

    private function createReading(array $changes = []): MeterReading
    {
        $response = $this->postJson($this->url(), $this->payload($changes))->assertCreated();

        return MeterReading::findOrFail($response->json('data.id'));
    }

    private function url(?MeterReading $reading = null): string
    {
        return "/api/owner/occupancies/{$this->occupancy->id}/meter-readings".($reading ? '/'.$reading->id : '');
    }

    private function invoice(string $month): Invoice
    {
        $tenant = Tenant::create(['full_name' => 'Đại diện', 'phone_number' => '0900123456']);
        $stay = TenantStay::create(['occupancy_id' => $this->occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01 00:00:00']);
        $representative = RepresentativeAssignment::create(['occupancy_id' => $this->occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01 00:00:00']);

        return Invoice::create([
            'invoice_number' => 'METER-'.fake()->unique()->numerify('#####'), 'occupancy_id' => $this->occupancy->id,
            'billing_month' => $month, 'representative_assignment_id' => $representative->id, 'issued_by' => $this->owner->id,
        ]);
    }
}
