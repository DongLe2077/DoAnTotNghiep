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
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class InvoiceBillingTest extends TestCase
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

    public function test_preview_is_not_persisted_and_issue_creates_immutable_invoice_with_access(): void
    {
        $occupancy = $this->occupancy();
        $preview = $this->preview($occupancy)->assertOk()->assertJsonPath('data.can_issue', true)
            ->assertJsonPath('data.total_amount', '3000000.00')->assertJsonPath('data.items.0.quantity', '1.000');
        $this->assertSame(0, Invoice::count());
        $issued = $this->issue($occupancy, $preview->json('data.preview_token'))->assertCreated()
            ->assertJsonPath('data.total_amount', '3000000.00')->assertJsonPath('data.payment_status', 'unpaid');
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, InvoiceItem::count());
        $this->assertSame(1, InvoiceAccessGrant::count());
        $this->assertSame($this->owner->id, Invoice::first()->issued_by);
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertConflict();
        $this->putJson('/api/owner/invoices/'.$issued->json('data.id'), [])->assertStatus(405);
        $this->deleteJson('/api/owner/invoices/'.$issued->json('data.id'))->assertStatus(405);
    }

    public function test_electricity_and_water_use_the_applied_price_snapshot(): void
    {
        $occupancy = $this->occupancy();
        $electric = $this->service($occupancy, 'metered', '3500', 'Điện');
        $water = $this->service($occupancy, 'metered', '18000', 'Nước');
        $this->reading($electric, '100.000', '140.125');
        $this->reading($water, '20', '23.500');
        $electric->service()->update(['default_unit_price' => '99000', 'name' => 'Điện mới']);
        $preview = $this->preview($occupancy)->assertOk()->assertJsonPath('data.can_issue', true)
            ->assertJsonPath('data.total_amount', '3203437.50')->assertJsonPath('data.items.1.unit_price', '3500.0000')
            ->assertJsonPath('data.items.1.quantity', '40.125')->assertJsonPath('data.items.2.quantity', '3.500');
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertCreated()->assertJsonPath('data.total_amount', '3203437.50');
    }

    public function test_missing_reading_blocks_publication_and_does_not_create_partial_invoice(): void
    {
        $occupancy = $this->occupancy();
        $this->service($occupancy);
        $preview = $this->preview($occupancy)->assertOk()->assertJsonPath('data.can_issue', false);
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertConflict();
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, InvoiceItem::count());
        $this->assertSame(0, InvoiceAccessGrant::count());
    }

    public function test_gap_overlap_or_wrong_meter_continuity_cannot_be_billed(): void
    {
        $occupancy = $this->occupancy();
        $service = $this->service($occupancy);
        $this->reading($service, '0', '10', '2026-01-01 00:00:00', '2026-01-15 00:00:00');
        $last = $this->reading($service, '11', '20', '2026-01-15 00:00:00', '2026-02-01 00:00:00');
        $this->preview($occupancy)->assertJsonPath('data.can_issue', false);
        $last->update(['previous_reading' => '10', 'period_start' => '2026-01-16 00:00:00']);
        $this->preview($occupancy)->assertJsonPath('data.can_issue', false);
        $last->update(['period_start' => '2026-01-14 00:00:00']);
        $this->preview($occupancy)->assertJsonPath('data.can_issue', false);
        $last->update(['period_start' => '2026-01-15 00:00:00']);
        $this->preview($occupancy)->assertJsonPath('data.can_issue', true)->assertJsonPath('data.total_amount', '3070000.00');
    }

    public function test_readings_crossing_month_boundary_are_not_silently_split(): void
    {
        $occupancy = $this->occupancy();
        $service = $this->service($occupancy);
        $this->reading($service, '0', '10', '2026-01-01 00:00:00', '2026-02-02 00:00:00');
        $this->preview($occupancy)->assertJsonPath('data.can_issue', false);
    }

    public function test_per_room_fee_is_prorated_precisely_without_rounding_month_quantity(): void
    {
        $occupancy = $this->occupancy(['started_at' => '2026-01-31 00:00:00']);
        $this->service($occupancy, 'per_room', '100000', 'Internet', '2026-01-31 00:00:00');
        $preview = $this->preview($occupancy)->assertOk()->assertJsonPath('data.can_issue', true)
            ->assertJsonPath('data.items.1.quantity', '1.000')->assertJsonPath('data.items.1.unit_price', '3225.8065')
            ->assertJsonPath('data.items.1.source_unit_price', '100000.0000')->assertJsonPath('data.total_amount', '3003225.81');
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertCreated()->assertJsonPath('data.total_amount', '3003225.81');
    }

    public function test_changed_flat_service_prices_are_split_at_the_effective_time(): void
    {
        $occupancy = $this->occupancy();
        $first = $this->service($occupancy, 'per_room', '31000', 'Rác');
        $first->update(['effective_until' => '2026-01-16 00:00:00']);
        OccupancyService::create([
            'occupancy_id' => $occupancy->id, 'service_id' => $first->service_id, 'billing_method' => 'per_room',
            'unit' => 'phòng', 'unit_price' => '62000', 'effective_from' => '2026-01-16 00:00:00',
        ]);
        $preview = $this->preview($occupancy)->assertOk()->assertJsonPath('data.can_issue', true)
            ->assertJsonPath('data.items.1.line_total', '15000.00')->assertJsonPath('data.items.2.line_total', '32000.00');
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertCreated()->assertJsonPath('data.total_amount', '3047000.00');
    }

    public function test_transfer_never_charges_rent_twice_even_when_later_room_is_billed_first(): void
    {
        $first = $this->occupancy(['ended_at' => '2026-01-15 00:00:00']);
        $second = $this->occupancy([
            'rental_group_id' => $first->rental_group_id, 'previous_occupancy_id' => $first->id,
            'started_at' => '2026-01-15 00:00:00', 'monthly_rent' => '4000000',
        ]);
        $preview = $this->preview($second)->assertJsonPath('data.total_amount', '3000000.00')
            ->assertJsonPath('data.rent_source_occupancy_id', $first->id);
        $this->issue($second, $preview->json('data.preview_token'))->assertCreated();
        $preview = $this->preview($first)->assertJsonPath('data.total_amount', '0.00')->assertJsonPath('data.can_issue', true);
        $this->issue($first, $preview->json('data.preview_token'))->assertCreated()->assertJsonPath('data.payment_status', 'paid');
        $this->assertEquals(3000000, InvoiceItem::where('item_type', 'rent')->sum('line_total'));
    }

    public function test_preview_token_must_be_refreshed_when_another_occupancy_bills_the_group_rent(): void
    {
        $first = $this->occupancy(['ended_at' => '2026-01-15 00:00:00']);
        $second = $this->occupancy(['rental_group_id' => $first->rental_group_id, 'started_at' => '2026-01-15 00:00:00']);
        $old = $this->preview($first)->json('data.preview_token');
        $this->issue($second, $this->preview($second)->json('data.preview_token'))->assertCreated();
        $this->issue($first, $old)->assertConflict();
        $this->assertSame(1, Invoice::count());
    }

    public function test_preview_token_changes_after_meter_correction_and_no_client_total_is_accepted(): void
    {
        $occupancy = $this->occupancy();
        $service = $this->service($occupancy);
        $reading = $this->reading($service, '0', '10');
        $old = $this->preview($occupancy)->json('data.preview_token');
        $reading->update(['current_reading' => '20']);
        $this->issue($occupancy, $old)->assertConflict();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/invoices", [
            'billing_month' => '2026-01', 'preview_token' => $old, 'total_amount' => '1', 'items' => [], 'issued_by' => 999,
        ])->assertUnprocessable();
        $this->assertSame(0, Invoice::count());
    }

    public function test_current_month_cannot_be_finalized_until_checkout(): void
    {
        $occupancy = $this->occupancy(['started_at' => '2026-03-01 00:00:00']);
        $this->preview($occupancy, '2026-03')->assertJsonPath('data.can_issue', false)->assertJsonPath('data.is_final_period', false);
        $occupancy->update(['ended_at' => '2026-03-03 00:00:00']);
        $occupancy->tenantStays()->update(['moved_out_at' => '2026-03-03 00:00:00']);
        $occupancy->representativeAssignments()->update(['ended_at' => '2026-03-03 00:00:00']);
        $preview = $this->preview($occupancy, '2026-03')->assertJsonPath('data.can_issue', true)->assertJsonPath('data.is_final_period', true);
        $this->issue($occupancy, $preview->json('data.preview_token'), '2026-03')->assertCreated();
    }

    public function test_closed_tenancy_uses_last_representative_and_can_issue_without_an_active_account(): void
    {
        $occupancy = $this->occupancy(['ended_at' => '2026-01-20 00:00:00']);
        $rep = $occupancy->representativeAssignments()->first();
        $rep->tenantStay->tenant->user->update(['is_active' => false]);
        $preview = $this->preview($occupancy)->assertJsonPath('data.can_issue', true)->assertJsonPath('data.recipient_user_id', null);
        $this->assertNotEmpty($preview->json('data.warnings'));
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertCreated();
        $this->assertSame(0, InvoiceAccessGrant::count());
        $this->assertSame($rep->id, Invoice::first()->representative_assignment_id);
    }

    public function test_no_representative_blocks_issue_with_actionable_preview(): void
    {
        $occupancy = $this->occupancy();
        $occupancy->representativeAssignments()->delete();
        $preview = $this->preview($occupancy)->assertJsonPath('data.can_issue', false);
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertConflict();
    }

    public function test_month_validation_and_tenancy_intersection(): void
    {
        $occupancy = $this->occupancy();
        foreach (['2026-13', '2026-1', '2026-01-01', '2026-04', '0000-01'] as $month) {
            $this->preview($occupancy, $month)->assertUnprocessable();
        }
        $this->preview($occupancy, '2025-12')->assertUnprocessable();
        $this->postJson("/api/owner/occupancies/{$occupancy->id}/invoices", ['billing_month' => '2026-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('preview_token');
    }

    public function test_extreme_valid_meter_values_cannot_overflow_invoice_generated_amount(): void
    {
        $occupancy = $this->occupancy();
        $service = $this->service($occupancy, 'metered', '99999999999999.9999');
        $this->reading($service, '0', '999999999999.999');
        $preview = $this->preview($occupancy)->assertJsonPath('data.can_issue', false);
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertConflict();
        $this->assertSame(0, Invoice::count());
    }

    public function test_zero_consumption_is_still_a_valid_source_line(): void
    {
        $occupancy = $this->occupancy();
        $this->reading($this->service($occupancy), '100', '100');
        $preview = $this->preview($occupancy)->assertJsonPath('data.can_issue', true)->assertJsonPath('data.items.1.line_total', '0.00');
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertCreated();
        $this->assertSame(2, InvoiceItem::count());
    }

    public function test_imported_meter_history_must_continue_across_adjacent_tariff_versions(): void
    {
        $occupancy = $this->occupancy();
        $first = $this->service($occupancy);
        $first->update(['effective_until' => '2026-01-15 00:00:00']);
        $second = OccupancyService::create([
            'occupancy_id' => $occupancy->id, 'service_id' => $first->service_id,
            'billing_method' => 'metered', 'unit' => 'kWh', 'unit_price' => '4000',
            'effective_from' => '2026-01-15 00:00:00',
        ]);
        $this->reading($first, '100', '110', '2026-01-01 00:00:00', '2026-01-15 00:00:00');
        $last = $this->reading($second, '111', '120', '2026-01-15 00:00:00', '2026-02-01 00:00:00');
        $this->preview($occupancy)->assertJsonPath('data.can_issue', false);
        $last->update(['previous_reading' => '110']);
        $preview = $this->preview($occupancy)->assertJsonPath('data.can_issue', true)->assertJsonPath('data.total_amount', '3075000.00');
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertCreated();
    }

    public function test_large_room_rent_is_blocked_before_it_overflows_the_line_price_column(): void
    {
        $occupancy = $this->occupancy(['monthly_rent' => '100000000000000']);
        $preview = $this->preview($occupancy)->assertJsonPath('data.can_issue', false);
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertConflict();
        $this->assertSame(0, Invoice::count());
    }

    public function test_publication_rolls_back_all_lines_and_grants_when_invoice_number_is_taken(): void
    {
        $occupancy = $this->occupancy();
        $other = $this->occupancy();
        Invoice::create([
            'invoice_number' => 'HD-202601-'.$occupancy->id,
            'occupancy_id' => $other->id, 'billing_month' => '2026-02-01',
            'representative_assignment_id' => $other->representativeAssignments()->value('id'),
            'issued_by' => $this->owner->id,
        ]);
        $preview = $this->preview($occupancy)->assertJsonPath('data.can_issue', true);
        $this->issue($occupancy, $preview->json('data.preview_token'))->assertConflict();
        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, InvoiceItem::count());
        $this->assertSame(0, InvoiceAccessGrant::count());
    }

    public function test_role_guards_apply_before_occupancy_lookup(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->postJson('/api/owner/occupancies/999999/invoices/preview', ['billing_month' => '2026-01'])->assertForbidden();
        $this->postJson('/api/owner/occupancies/999999/invoices', ['billing_month' => '2026-01'])->assertForbidden();
        auth('web')->forgetUser();
        auth('web')->logout();
        $this->postJson('/api/owner/occupancies/999999/invoices/preview', [])->assertUnauthorized();
    }

    private function preview(Occupancy $occupancy, string $month = '2026-01')
    {
        return $this->postJson("/api/owner/occupancies/{$occupancy->id}/invoices/preview", ['billing_month' => $month]);
    }

    private function issue(Occupancy $occupancy, string $token, string $month = '2026-01')
    {
        return $this->postJson("/api/owner/occupancies/{$occupancy->id}/invoices", ['billing_month' => $month, 'preview_token' => $token]);
    }

    private function occupancy(array $overrides = []): Occupancy
    {
        $room = Room::create(['room_number' => 'B-'.(Room::count() + 1), 'capacity' => 4, 'default_monthly_rent' => '3000000', 'operational_status' => 'available']);
        $group = isset($overrides['rental_group_id']) ? RentalGroup::findOrFail($overrides['rental_group_id']) : RentalGroup::create(['group_code' => 'BILL-'.(RentalGroup::count() + 1)]);
        $occupancy = Occupancy::create(array_merge(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01 00:00:00', 'monthly_rent' => '3000000'], $overrides));
        $tenant = Tenant::create(['full_name' => 'Người thuê kiểm thử', 'phone_number' => '0900000000']);
        User::factory()->create(['role' => 'tenant', 'tenant_id' => $tenant->id]);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => $occupancy->started_at, 'moved_out_at' => $occupancy->ended_at]);
        RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => $occupancy->started_at, 'ended_at' => $occupancy->ended_at]);

        return $occupancy;
    }

    private function service(Occupancy $occupancy, string $method = 'metered', string $price = '3500', string $name = 'Điện', string $from = '2026-01-01 00:00:00'): OccupancyService
    {
        $service = Service::create(['service_code' => 'BILL-SVC-'.(Service::count() + 1), 'name' => $name, 'billing_method' => $method, 'unit' => 'đơn vị', 'default_unit_price' => $price, 'is_active' => true]);

        return OccupancyService::create(['occupancy_id' => $occupancy->id, 'service_id' => $service->id, 'effective_from' => $from, 'unit_price' => $price, 'billing_method' => $method, 'unit' => 'đơn vị']);
    }

    private function reading(OccupancyService $service, string $previous, string $current, string $from = '2026-01-01 00:00:00', string $until = '2026-02-01 00:00:00'): MeterReading
    {
        return MeterReading::create(['occupancy_service_id' => $service->id, 'period_start' => $from, 'period_end' => $until, 'previous_reading' => $previous, 'current_reading' => $current, 'recorded_by' => $this->owner->id]);
    }
}
