<?php

namespace Tests\Feature;

use App\Models\RentalGroup;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyWorkspaces;
use App\Services\TenancyLifecycle;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class CheckoutApplicationTest extends TestCase
{
    use CreatesCashBookSchema;

    private const OWNER = '/api/owner/checkout-requests';

    private const TENANT = '/api/tenant/checkout-requests';

    private User $owner;

    private User $representative;

    private User $member;

    private int $propertyId;

    private int $occupancyId;

    private int $destinationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(13, 0));
        $this->createCashBookSchema();
        foreach ([
            '2026_11_09_000001_create_payos_checkout_tables',
            '2026_11_13_000001_create_scheduled_deletions',
            '2026_11_15_000001_add_property_ownership_foundation',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        (require database_path('migrations/2026_11_23_000003_create_checkout_requests.php'))->up();
        config(['tenancy.enabled' => true]);
        $this->owner = User::factory()->create();
        $this->propertyId = app(PropertyWorkspaces::class)->create($this->owner, ['name' => 'Khu A', 'address' => 'Đường A'])->id;
        app(PropertyContext::class)->run($this->propertyId, function () {
            $this->representative = $this->createResident('Người đại diện');
            $this->member = $this->createResident('Thành viên');
            $room = Room::create(['room_number' => 'A1', 'capacity' => 2, 'default_monthly_rent' => 2000]);
            $this->destinationId = Room::create(['room_number' => 'A2', 'capacity' => 2, 'default_monthly_rent' => 2000])->id;
            $group = RentalGroup::create(['group_code' => 'G1']);
            $this->occupancyId = app(TenancyLifecycle::class)->checkIn([
                'room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-11-01 09:00:00',
                'tenant_ids' => [$this->representative->tenant_id, $this->member->tenant_id],
                'representative_tenant_id' => $this->representative->tenant_id,
            ])->id;
        });
    }

    public function test_only_the_representative_can_send_or_withdraw_the_single_pending_request(): void
    {
        $this->actingAs($this->member)->postJson($this->submitPath(), [], $this->scope())->assertForbidden();

        $id = $this->submit();
        $this->postJson($this->submitPath(), [], $this->scope())->assertConflict();
        $this->assertDatabaseCount('checkout_requests', 1);

        $this->actingAs($this->member)->getJson(self::TENANT, $this->scope())->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson(self::TENANT.'/'.$id, [], $this->scope())->assertNotFound();

        $this->actingAs($this->representative)->deleteJson(self::TENANT.'/'.$id, [], $this->scope())
            ->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->deleteJson(self::TENANT.'/'.$id, [], $this->scope())->assertConflict();
        $this->assertNotSame($id, $this->submit());
    }

    public function test_owner_of_the_selected_property_can_reject_with_a_reason(): void
    {
        $id = $this->submit();
        $other = User::factory()->create();
        $otherPropertyId = app(PropertyWorkspaces::class)->create($other, ['name' => 'Khu B', 'address' => 'Đường B'])->id;

        $this->actingAs($other)->getJson(self::OWNER, $this->scope($otherPropertyId))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson(self::OWNER.'/'.$id.'/reject', ['reason' => 'Lý do thử'], $this->scope($otherPropertyId))->assertNotFound();
        $this->postJson(self::OWNER.'/'.$id.'/confirm', ['ended_at' => '2026-11-22 12:00:00'], $this->scope($otherPropertyId))->assertNotFound();

        $this->actingAs($this->owner)->getJson(self::OWNER, $this->scope())->assertOk()
            ->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.room_number', 'A1');
        $this->postJson(self::OWNER.'/'.$id.'/reject', ['reason' => '  '], $this->scope())->assertUnprocessable();
        $this->postJson(self::OWNER.'/'.$id.'/reject', ['reason' => 'Chưa bàn giao chìa khóa'], $this->scope())
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->actingAs($this->representative)->getJson(self::TENANT, $this->scope())->assertOk()
            ->assertJsonPath('data.0.rejection_reason', 'Chưa bàn giao chìa khóa');
        $this->assertDatabaseHas('occupancies', ['id' => $this->occupancyId, 'ended_at' => null]);
    }

    public function test_confirmation_uses_checkout_validation_and_keeps_history_accessible(): void
    {
        $id = $this->submit();
        $this->actingAs($this->owner)->postJson(self::OWNER.'/'.$id.'/confirm', ['ended_at' => '2026-11-01 08:00:00'], $this->scope())
            ->assertUnprocessable();
        $this->assertDatabaseHas('checkout_requests', ['id' => $id, 'status' => 'pending']);
        $this->assertDatabaseHas('occupancies', ['id' => $this->occupancyId, 'ended_at' => null]);

        $at = '2026-11-22 12:00:00';
        $this->postJson(self::OWNER.'/'.$id.'/confirm', ['ended_at' => $at], $this->scope())->assertOk()
            ->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.ended_at', $at);
        $this->postJson(self::OWNER.'/'.$id.'/confirm', ['ended_at' => $at], $this->scope())->assertConflict();
        $this->assertSame(2, DB::table('tenant_stays')->where('occupancy_id', $this->occupancyId)->where('moved_out_at', $at)->count());
        $this->assertDatabaseHas('representative_assignments', ['occupancy_id' => $this->occupancyId, 'ended_at' => $at]);
        $this->getJson('/api/owner/occupancies/'.$this->occupancyId, $this->scope())->assertOk()->assertJsonPath('data.ended_at', $at);

        $this->actingAs($this->representative)->getJson('/api/tenant/current-stay', $this->scope())->assertOk()->assertJsonPath('data', null);
        $this->getJson('/api/tenant/stays', $this->scope())->assertOk()->assertJsonPath('data.0.moved_out_at', $at);
        $this->getJson(self::TENANT, $this->scope())->assertOk()->assertJsonPath('data.0.status', 'confirmed');
        $this->postJson($this->submitPath(), [], $this->scope())->assertConflict();
    }

    public function test_transfer_closes_the_old_request_without_touching_the_new_stay(): void
    {
        $id = $this->submit();
        $next = $this->actingAs($this->owner)->postJson('/api/owner/occupancies/'.$this->occupancyId.'/transfer', [
            'room_id' => $this->destinationId, 'transferred_at' => '2026-11-22 12:00:00',
        ], $this->scope())->assertCreated()->json('data.id');
        $this->getJson(self::OWNER.'?status=pending', $this->scope())->assertOk()->assertJsonCount(0, 'data');
        $this->postJson(self::OWNER.'/'.$id.'/confirm', ['ended_at' => '2026-11-23 12:00:00'], $this->scope())->assertConflict();
        $this->assertDatabaseHas('occupancies', ['id' => $next, 'ended_at' => null]);

        $this->actingAs($this->representative)->getJson(self::TENANT, $this->scope())->assertOk()->assertJsonPath('data.0.status', 'closed');
        $this->postJson('/api/tenant/occupancies/'.$next.'/checkout-requests', [], $this->scope())->assertCreated();
    }

    private function createResident(string $name): User
    {
        $resident = User::factory()->create(['role' => 'tenant']);
        $tenant = Tenant::create(['full_name' => $name, 'phone_number' => '0900000000']);
        DB::table('property_memberships')->insert([
            'property_id' => $this->propertyId, 'user_id' => $resident->id, 'tenant_id' => $tenant->id,
            'role' => 'tenant', 'status' => 'approved',
        ]);

        return $resident;
    }

    private function scope(?int $propertyId = null): array
    {
        return ['X-Property-ID' => (string) ($propertyId ?? $this->propertyId)];
    }

    private function submitPath(): string
    {
        return '/api/tenant/occupancies/'.$this->occupancyId.'/checkout-requests';
    }

    private function submit(): int
    {
        return $this->actingAs($this->representative)->postJson($this->submitPath(), [], $this->scope())
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
    }
}
