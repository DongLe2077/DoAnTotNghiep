<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyWorkspaces;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class RoomMarketplaceTest extends TestCase
{
    use CreatesCashBookSchema;

    private const USER_LISTINGS = '/api/marketplace/listings';

    private const USER_REQUESTS = '/api/marketplace/requests';

    private const OWNER_LISTINGS = '/api/owner/marketplace/listings';

    private const OWNER_REQUESTS = '/api/owner/marketplace/requests';

    private User $owner;

    private User $otherOwner;

    private int $property;

    private int $otherProperty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(8, 0));
        $this->createCashBookSchema();
        foreach ([
            '2026_11_09_000001_create_payos_checkout_tables',
            '2026_11_13_000001_create_scheduled_deletions',
            '2026_11_15_000001_add_property_ownership_foundation',
            '2026_11_15_000002_create_property_payment_settings',
            '2026_11_15_000003_scope_catalog_unique_keys',
            '2026_11_16_000001_create_property_onboarding',
            '2026_11_16_000002_create_room_marketplace',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        config(['tenancy.enabled' => true]);
        $this->owner = User::factory()->create();
        $this->otherOwner = User::factory()->create();
        $this->property = app(PropertyWorkspaces::class)->create($this->owner, ['name' => 'Khu A', 'address' => 'Đường A'])->id;
        $this->otherProperty = app(PropertyWorkspaces::class)->create($this->otherOwner, ['name' => 'Khu B', 'address' => 'Đường B'])->id;
    }

    public function test_public_catalog_filters_hides_and_serves_only_public_photo(): void
    {
        $room = $this->room(2);
        $image = UploadedFile::fake()->createWithContent('room.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO9WcXUAAAAASUVORK5CYII=',
        ));
        $id = $this->actingAs($this->owner)->post(self::OWNER_LISTINGS, $this->listingData() + [
            'room_id' => $room, 'photo' => $image,
        ], $this->scope())->assertCreated()->json('data.id');

        $this->actingAs($this->otherOwner)->postJson(self::OWNER_LISTINGS.'/'.$id, $this->listingData(), $this->scope($this->otherProperty))->assertNotFound();
        Auth::guard('web')->logout();
        $this->getJson(self::USER_LISTINGS.'?area=Quan+1&min_price=1900&max_price=2100&people=2')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.spots', 2)
            ->assertJsonMissingPath('data.0.photo_base64')->assertJsonMissingPath('data.0.owner_user_id');
        $this->getJson(self::USER_LISTINGS.'?people=3')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::USER_LISTINGS.'?min_price=2100')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::USER_LISTINGS.'?area=%25')->assertOk()->assertJsonPath('meta.total', 0);
        $this->get(self::USER_LISTINGS.'/'.$id.'/photo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->postJson(self::USER_LISTINGS.'/'.$id.'/requests', $this->requestData('viewing'))->assertUnauthorized();

        DB::table('rooms')->where('id', $room)->update(['operational_status' => 'maintenance']);
        $this->getJson(self::USER_LISTINGS.'/'.$id)->assertNotFound();
        DB::table('rooms')->where('id', $room)->update(['operational_status' => 'available']);
        DB::table('scheduled_deletions')->insert([
            'property_id' => $this->property, 'record_type' => 'rooms', 'record_id' => $room,
            'label' => 'Phòng thử', 'records' => json_encode(['rooms' => [$room]]), 'purge_at' => now()->addDays(3),
        ]);
        $this->getJson(self::USER_LISTINGS.'/'.$id)->assertNotFound();

        DB::table('scheduled_deletions')->delete();
        $this->actingAs($this->owner)->deleteJson(self::OWNER_LISTINGS.'/'.$id, [], $this->scope())->assertNoContent();
        $this->get(self::USER_LISTINGS.'/'.$id.'/photo')->assertNotFound();
        $this->getJson(self::OWNER_LISTINGS, $this->scope())->assertOk()->assertJsonPath('data.0.accepting', false);
    }

    public function test_viewing_reschedule_and_completed_visit_do_not_reserve_space(): void
    {
        $listing = $this->listing($this->room());
        $user = User::factory()->create(['role' => 'tenant']);
        $other = User::factory()->create(['role' => 'tenant']);
        $id = $this->actingAs($user)->postJson(self::USER_LISTINGS.'/'.$listing.'/requests', $this->requestData('viewing'))
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->postJson(self::USER_LISTINGS.'/'.$listing.'/requests', $this->requestData('viewing'))->assertConflict();
        $this->actingAs($other)->putJson(self::USER_REQUESTS.'/'.$id, ['action' => 'cancel'])->assertNotFound();
        $this->getJson(self::USER_REQUESTS)->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($this->otherOwner)->putJson(self::OWNER_REQUESTS.'/'.$id, ['action' => 'confirm'], $this->scope($this->otherProperty))->assertNotFound();
        $this->ownerAction($id, 'propose', ['appointment_at' => '2026-11-21 09:00:00', 'owner_note' => 'Hẹn tại cổng'])
            ->assertOk()->assertJsonPath('data.status', 'proposed');
        $this->actingAs($user)->putJson(self::USER_REQUESTS.'/'.$id, ['action' => 'accept_time'])
            ->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->ownerAction($id, 'viewed')->assertConflict();
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(10, 0));
        $this->ownerAction($id, 'viewed')->assertOk()->assertJsonPath('data.status', 'viewed');
        $this->assertSame(
            ['pending', 'proposed', 'confirmed', 'viewed'],
            DB::table('room_request_events')->where('request_id', $id)->orderBy('id')->pluck('status')->all(),
        );
        $this->assertDatabaseHas('room_request_events', [
            'request_id' => $id, 'actor_id' => $user->id, 'status' => 'confirmed', 'appointment_at' => '2026-11-21 09:00:00',
        ]);
        $this->assertDatabaseCount('occupancies', 0);
        $this->getJson(self::USER_LISTINGS.'/'.$listing)->assertOk()->assertJsonPath('data.spots', 1);
    }

    public function test_move_in_requires_approval_and_rechecks_room_capacity_before_creating_records(): void
    {
        $listing = $this->listing($this->room());
        $first = User::factory()->create(['role' => 'tenant']);
        $second = User::factory()->create(['role' => 'tenant']);
        $firstRequest = $this->rentalRequest($first, $listing);
        $secondRequest = $this->rentalRequest($second, $listing);
        $this->moveIn($firstRequest, true)->assertConflict();
        $this->ownerAction($firstRequest, 'accept')->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->ownerAction($secondRequest, 'accept')->assertOk();
        $this->moveIn($firstRequest, false)->assertUnprocessable();
        $this->assertDatabaseMissing('property_memberships', ['property_id' => $this->property, 'user_id' => $first->id]);
        $this->assertDatabaseCount('occupancies', 0);
        $this->getJson(self::USER_LISTINGS.'/'.$listing)->assertOk()->assertJsonPath('data.spots', 1);

        $occupancy = $this->moveIn($firstRequest, true)->assertOk()->assertJsonPath('data.status', 'moved_in')->json('data.occupancy_id');
        $this->moveIn($firstRequest, true)->assertOk()->assertJsonPath('data.occupancy_id', $occupancy);
        $this->moveIn($secondRequest, true)->assertConflict();
        $this->assertSame(1, DB::table('room_request_events')->where('request_id', $firstRequest)->where('status', 'moved_in')->count());
        $this->assertDatabaseCount('occupancies', 1);
        $this->assertDatabaseCount('tenants', 1);
        $this->actingAs($this->owner)->getJson('/api/owner/marketplace/requests?kind=rent&status=moved_in', $this->scope())
            ->assertOk()->assertJsonPath('data.0.tenant_name', DB::table('tenants')->value('full_name'));
        $this->assertDatabaseHas('room_requests', ['id' => $firstRequest, 'membership_approved_at_move_in' => true, 'handled_by' => $this->owner->id]);
        $this->assertDatabaseHas('property_memberships', ['property_id' => $this->property, 'user_id' => $first->id, 'role' => 'member', 'status' => 'approved']);
        $this->assertDatabaseMissing('property_memberships', ['property_id' => $this->property, 'user_id' => $second->id]);
        $this->getJson(self::USER_LISTINGS.'/'.$listing)->assertNotFound();
        $this->actingAs($first)->getJson('/api/tenant/invoices', $this->scope())->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_sharing_only_publishes_occupied_rooms_that_allow_sharing(): void
    {
        $room = $this->room(2);
        $listing = $this->listing($room);
        $first = User::factory()->create(['role' => 'tenant']);
        $second = User::factory()->create(['role' => 'tenant']);
        $firstRequest = $this->rentalRequest($first, $listing);
        $this->ownerAction($firstRequest, 'accept')->assertOk();
        $occupancy = $this->moveIn($firstRequest, true)->assertOk()->json('data.occupancy_id');
        $this->getJson(self::USER_LISTINGS.'/'.$listing)->assertNotFound();
        $this->actingAs($this->owner)->postJson(self::OWNER_LISTINGS.'/'.$listing,
            array_replace($this->listingData(), ['sharing' => true]), $this->scope())->assertOk();
        $this->getJson(self::USER_LISTINGS.'/'.$listing)->assertOk()->assertJsonPath('data.spots', 1);
        $secondRequest = $this->rentalRequest($second, $listing);
        $this->ownerAction($secondRequest, 'accept')->assertOk();
        $this->moveIn($secondRequest, true)->assertOk()->assertJsonPath('data.occupancy_id', $occupancy);
        $this->assertDatabaseCount('occupancies', 1);
        $this->assertDatabaseCount('tenant_stays', 2);
        $this->getJson(self::USER_LISTINGS.'/'.$listing)->assertNotFound();
    }

    private function scope(?int $property = null): array
    {
        return ['X-Property-ID' => (string) ($property ?? $this->property), 'Accept' => 'application/json'];
    }

    private function room(int $capacity = 1): int
    {
        return app(PropertyContext::class)->run($this->property, fn () => Room::create([
            'room_number' => 'TEST-MARKET', 'capacity' => $capacity,
            'default_monthly_rent' => 2000, 'operational_status' => 'available',
        ])->id);
    }

    private function listingData(): array
    {
        return ['title' => 'Phòng A thử', 'area' => 'Quan 1', 'description' => 'Phòng sáng', 'sharing' => false, 'published' => true];
    }

    private function listing(int $room): int
    {
        return $this->actingAs($this->owner)->postJson(self::OWNER_LISTINGS, $this->listingData() + ['room_id' => $room], $this->scope())
            ->assertCreated()->json('data.id');
    }

    private function requestData(string $kind): array
    {
        return [
            'kind' => $kind, 'people' => 1, 'preferred_at' => '2026-11-20 09:00:00',
            'contact_name' => 'Khách thử', 'contact_phone' => '0900000021',
        ];
    }

    private function rentalRequest(User $user, int $listing): int
    {
        return $this->actingAs($user)->postJson(self::USER_LISTINGS.'/'.$listing.'/requests', $this->requestData('rent'))
            ->assertCreated()->json('data.id');
    }

    private function ownerAction(int $id, string $action, array $data = [])
    {
        return $this->actingAs($this->owner)->putJson(self::OWNER_REQUESTS.'/'.$id, $data + ['action' => $action], $this->scope());
    }

    private function moveIn(int $id, bool $approve)
    {
        return $this->actingAs($this->owner)->postJson(self::OWNER_REQUESTS.'/'.$id.'/move-in', [
            'started_at' => now()->toDateTimeString(), 'approve_membership' => $approve,
        ], $this->scope());
    }
}
