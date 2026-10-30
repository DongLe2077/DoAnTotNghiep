<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\MeterReadingRequestRecord;
use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class BulkMeterReadingsTest extends TestCase
{
    use CreatesBillingSchema;

    private User $owner;

    private Occupancy $occupancy;

    private OccupancyService $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 5)->setTime(12, 0));
        $this->createBillingSchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
        $this->occupancy = $this->occupancy();
        $this->period = $this->period($this->occupancy);
    }

    public function test_listing_has_unknown_first_baseline_and_paginates_filters_without_writing(): void
    {
        $other = $this->period($this->occupancy());
        $this->getJson('/api/owner/meter-readings/bulk?billing_month=2026-01&per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.previous_reading', null)->assertJsonPath('data.0.baseline_known', false)
            ->assertJsonPath('data.0.can_record', true)->assertJsonPath('data.0.period_start', '2026-01-01 00:00:00')
            ->assertJsonPath('data.0.period_end', '2026-02-01 00:00:00');
        $this->getJson('/api/owner/meter-readings/bulk?billing_month=2026-01&occupancy_service_id='.$other->id)
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.occupancy_id', $other->occupancy_id);
        $this->getJson('/api/owner/meter-readings/bulk?billing_month=2026-01&room_id='.$this->occupancy->room_id)
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.occupancy_service_id', $this->period->id);
        $this->getJson('/api/owner/meter-readings/bulk?billing_month=2026-01&q=does-not-exist')->assertJsonCount(0, 'data');
        $this->assertSame(0, MeterReading::count());
        $this->assertSame(0, MeterReadingRequestRecord::count());
    }

    public function test_each_row_is_independent_and_preserves_exact_consumption(): void
    {
        $other = $this->period($this->occupancy());
        $first = $this->payload($this->period);
        $this->submit($first)->assertCreated()->assertJsonPath('data.consumption', '40.125')
            ->assertJsonPath('data.previous_reading', '100.125')->assertJsonPath('replayed', false);
        $this->submit(array_replace($this->payload($other), ['current_reading' => '1']))->assertUnprocessable()->assertJsonValidationErrors('current_reading');
        $this->assertSame(1, MeterReading::count());
        $this->assertSame(1, MeterReadingRequestRecord::count());
        $this->submit(array_replace($this->payload($other), ['current_reading' => '150']))->assertCreated();
        $this->assertSame(2, MeterReading::count());
    }

    public function test_same_request_replays_after_edit_and_deletion_without_recreating_old_data(): void
    {
        $payload = $this->payload($this->period);
        $first = $this->submit($payload)->assertCreated();
        $id = $first->json('reading_id');
        $this->putJson('/api/owner/occupancies/'.$this->occupancy->id.'/meter-readings/'.$id,
            array_replace($payload, ['current_reading' => '150.500']))->assertOk();
        $this->submit($payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('reading_changed', true)
            ->assertJsonPath('data.current_reading', '150.500')->assertJsonPath('reading_id', $id);
        $this->deleteJson('/api/owner/occupancies/'.$this->occupancy->id.'/meter-readings/'.$id)->assertNoContent();
        $this->submit($payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data', null)
            ->assertJsonPath('reading_id', $id)->assertJsonPath('reading_deleted', true);
        $this->assertSame(0, MeterReading::count());
        $this->assertSame(1, MeterReadingRequestRecord::count());
        $this->submit(array_replace($payload, ['current_reading' => '160']))->assertConflict();
    }

    public function test_context_rejects_changes_in_readings_prices_or_occupancy(): void
    {
        $payload = $this->payload($this->period);
        $this->period->update(['unit_price' => '4000']);
        $this->submit($payload)->assertConflict();
        $payload = $this->payload($this->period);
        $this->occupancy->update(['ended_at' => '2026-01-20 00:00:00']);
        $this->submit($payload)->assertConflict();
        $this->occupancy->update(['ended_at' => null]);
        $payload = $this->payload($this->period);
        MeterReading::create(['occupancy_service_id' => $this->period->id, 'period_start' => '2026-01-01 00:00:00',
            'period_end' => '2026-01-15 00:00:00', 'previous_reading' => '100.125', 'current_reading' => '110', 'recorded_by' => $this->owner->id]);
        $this->submit($payload)->assertConflict();
        $this->assertSame(0, MeterReadingRequestRecord::count());
    }

    public function test_price_intervals_require_fresh_context_and_continue_same_meter_baseline(): void
    {
        $this->period->update(['effective_until' => '2026-01-15 00:00:00']);
        $second = $this->period($this->occupancy, ['service_id' => $this->period->service_id,
            'effective_from' => '2026-01-15 00:00:00', 'unit_price' => '4500']);
        $stale = $this->payload($second);
        $this->submit($this->payload($this->period))->assertCreated()->assertJsonPath('data.period_end', '2026-01-15 00:00:00');
        $this->submit($stale)->assertConflict();
        $row = $this->row($second);
        $this->assertSame('140.250', $row['previous_reading']);
        $this->assertTrue($row['baseline_known']);
        $this->assertSame('2026-01-15 00:00:00', $row['period_start']);
        $this->submit($this->payload($second, ['current_reading' => '160.125']))->assertCreated()->assertJsonPath('data.consumption', '19.875');
        $this->assertSame(2, MeterReading::count());
    }

    public function test_closed_stays_are_listed_and_cannot_extend_beyond_checkout(): void
    {
        $this->occupancy->update(['ended_at' => '2026-01-20 12:00:00']);
        $row = $this->row($this->period);
        $this->assertTrue($row['can_record']);
        $this->assertSame('2026-01-20 12:00:00', $row['period_end']);
        $this->submit($this->payload($this->period, ['period_end' => '2026-02-01 00:00:00']))->assertUnprocessable()->assertJsonValidationErrors('period_end');
        $this->submit($this->payload($this->period))->assertCreated();
        $this->getJson('/api/owner/meter-readings/bulk?billing_month=2026-02')->assertJsonCount(0, 'data');
    }

    public function test_adjacent_price_period_cannot_skip_unfinished_prior_interval(): void
    {
        $this->period->update(['effective_until' => '2026-01-15 00:00:00']);
        $second = $this->period($this->occupancy, ['service_id' => $this->period->service_id,
            'effective_from' => '2026-01-15 00:00:00', 'unit_price' => '4500']);
        $row = $this->row($second);
        $this->assertFalse($row['can_record']);
        $this->assertStringContainsString('Khoảng giá liền trước', $row['blocked_reason']);
        $this->submit($this->payload($second))->assertConflict();
        $this->assertSame(0, MeterReading::count());

        $this->submit($this->payload($this->period, ['period_end' => '2026-01-10 00:00:00', 'current_reading' => '110']))->assertCreated();
        $this->assertFalse($this->row($second)['can_record']);
        $this->submit($this->payload($second))->assertConflict();

        $this->submit($this->payload($this->period, ['current_reading' => '120']))->assertCreated()
            ->assertJsonPath('data.period_start', '2026-01-10 00:00:00')->assertJsonPath('data.period_end', '2026-01-15 00:00:00');
        $row = $this->row($second);
        $this->assertTrue($row['can_record']);
        $this->assertSame('120.000', $row['previous_reading']);
        $this->submit($this->payload($second, ['current_reading' => '130']))->assertCreated()->assertJsonPath('data.consumption', '10.000');
        $this->assertSame(3, MeterReading::count());
    }

    public function test_missing_prior_month_and_invoiced_month_are_blocked_truthfully(): void
    {
        MeterReading::create(['occupancy_service_id' => $this->period->id, 'period_start' => '2026-01-01 00:00:00',
            'period_end' => '2026-01-15 00:00:00', 'previous_reading' => '100', 'current_reading' => '110', 'recorded_by' => $this->owner->id]);
        $row = $this->row($this->period, '2026-02');
        $this->assertFalse($row['can_record']);
        $this->assertStringContainsString('Còn thiếu kỳ chỉ số', $row['blocked_reason']);
        $this->submit($this->payload($this->period, ['billing_month' => '2026-02', 'period_start' => '2026-02-01 00:00:00',
            'period_end' => '2026-03-01 00:00:00', 'previous_reading' => '110'], '2026-02'))->assertUnprocessable();
        $this->invoice();
        $row = $this->row($this->period);
        $this->assertFalse($row['can_record']);
        $this->assertNotNull($row['invoice_id']);
        $this->submit($this->payload($this->period))->assertConflict();
    }

    public function test_unknown_baseline_is_never_assumed_and_zero_consumption_is_valid(): void
    {
        $this->assertNull($this->row($this->period)['previous_reading']);
        $values = $this->payload($this->period, ['previous_reading' => '999999999999.999', 'current_reading' => '999999999999.999']);
        $this->submit($values)->assertCreated()->assertJsonPath('data.consumption', '0.000');
    }

    public function test_keys_are_scoped_to_actor_and_complete_original_payload(): void
    {
        $payload = $this->payload($this->period);
        $this->submit($payload)->assertCreated();
        $this->submit(array_replace($payload, ['current_reading' => '150']))->assertConflict();
        $other = $this->period($this->occupancy());
        $this->submit(array_replace($this->payload($other), ['idempotency_key' => $payload['idempotency_key']]))->assertConflict();
        $this->actingAs(User::factory()->create(), 'web');
        $this->submit($payload)->assertConflict();
        $this->assertSame(1, MeterReading::count());
        $this->assertSame(1, MeterReadingRequestRecord::count());
    }

    public function test_failed_dedup_insert_rolls_back_reading_and_history_is_not_discarded(): void
    {
        $payload = $this->payload($this->period);
        DB::unprepared("CREATE TRIGGER reject_meter_request BEFORE INSERT ON meter_reading_requests BEGIN SELECT RAISE(ABORT, 'fixture request failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->submit($payload);
            $this->fail('Expected request recording failure.');
        } catch (QueryException) {
            $this->assertSame(0, MeterReading::count());
            $this->assertSame(0, MeterReadingRequestRecord::count());
        }
        DB::unprepared('DROP TRIGGER reject_meter_request');
        $this->submit($payload)->assertCreated();
        try {
            (require database_path('migrations/2026_10_28_000002_create_meter_reading_requests.php'))->down();
            $this->fail('Expected migration history guard.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Cannot discard', $error->getMessage());
            $this->assertSame(1, MeterReadingRequestRecord::count());
        }
    }

    public function test_guests_tenants_inactive_accounts_and_forged_values_are_rejected(): void
    {
        $payload = $this->payload($this->period);
        foreach ([['recorded_by' => 1], ['consumption' => '99'], ['current_reading' => '-1'], ['current_reading' => '140.1234'],
            ['billing_month' => '2026-13'], ['period_start' => '2025-12-31 23:59:59'], ['occupancy_service_id' => 999999]] as $change) {
            $response = $this->submit(array_replace($payload, $change));
            $this->assertContains($response->status(), [404, 409, 422]);
        }
        $this->getJson('/api/owner/meter-readings/bulk?billing_month=2026-13&per_page=51')->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->getJson('/api/owner/meter-readings/bulk?billing_month=2026-01')->assertForbidden();
        $this->submit($payload)->assertForbidden();
        $this->owner->update(['is_active' => false]);
        $this->actingAs($this->owner, 'web');
        $this->submit($payload)->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->submit($payload)->assertUnauthorized();
        $this->assertSame(0, MeterReading::count());
    }

    private function row(OccupancyService $period, string $month = '2026-01'): array
    {
        return $this->getJson('/api/owner/meter-readings/bulk?billing_month='.$month.'&occupancy_service_id='.$period->id)
            ->assertOk()->assertJsonCount(1, 'data')->json('data.0');
    }

    private function payload(OccupancyService $period, array $changes = [], string $month = '2026-01'): array
    {
        $row = $this->row($period, $month);

        return array_replace(['billing_month' => $month, 'occupancy_id' => $period->occupancy_id, 'occupancy_service_id' => $period->id,
            'period_start' => $row['period_start'], 'period_end' => $row['period_end'],
            'previous_reading' => $row['previous_reading'] ?? '100.125', 'current_reading' => '140.250',
            'context_token' => $row['context_token'], 'idempotency_key' => (string) Str::uuid()], $changes);
    }

    private function submit(array $payload)
    {
        return $this->postJson('/api/owner/meter-readings/bulk/rows', $payload);
    }

    private function occupancy(): Occupancy
    {
        $number = 'BULK-'.(Room::count() + 1);
        $room = Room::create(['room_number' => $number, 'capacity' => 2, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => $number]);

        return Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01 00:00:00', 'monthly_rent' => '3000000']);
    }

    private function period(Occupancy $occupancy, array $changes = []): OccupancyService
    {
        $service = isset($changes['service_id']) ? Service::findOrFail($changes['service_id']) : Service::create(['service_code' => 'BULK-'.(Service::count() + 1),
            'name' => 'Điện', 'unit' => 'kWh', 'billing_method' => 'metered', 'default_unit_price' => '3500', 'is_active' => true]);

        return OccupancyService::create(array_replace(['occupancy_id' => $occupancy->id, 'service_id' => $service->id,
            'effective_from' => '2026-01-01 00:00:00', 'unit_price' => '3500', 'billing_method' => 'metered', 'unit' => 'kWh'], $changes));
    }

    private function invoice(): Invoice
    {
        $tenant = Tenant::create(['full_name' => 'Đại diện thử', 'phone_number' => '0900000000']);
        $stay = TenantStay::create(['occupancy_id' => $this->occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01 00:00:00']);
        $representative = RepresentativeAssignment::create(['occupancy_id' => $this->occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01 00:00:00']);

        return Invoice::create(['invoice_number' => 'BULK-INVOICE', 'occupancy_id' => $this->occupancy->id, 'billing_month' => '2026-01-01',
            'representative_assignment_id' => $representative->id, 'issued_by' => $this->owner->id]);
    }
}
