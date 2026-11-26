<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tenancy\PropertyAccess;
use App\Services\Tenancy\PropertyQueries;
use App\Services\Tenancy\PropertyWorkspaces;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesCatalogSchema;
use Tests\TestCase;

class PropertyOwnershipFoundationTest extends TestCase
{
    use CreatesCatalogSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCatalogSchema();
        (require database_path('migrations/2026_11_15_000001_add_property_ownership_foundation.php'))->up();
    }

    private function property(User $owner, string $name = 'Khu A'): int
    {
        return app(PropertyWorkspaces::class)->create($owner, ['name' => $name, 'address' => 'Địa chỉ mẫu'])->id;
    }

    private function room(int $propertyId, string $name): int
    {
        return DB::table('rooms')->insertGetId(['property_id' => $propertyId, 'room_number' => $name,
            'capacity' => 2, 'default_monthly_rent' => 2800000, 'operational_status' => 'available']);
    }

    private function deny(callable $operation, int $status = 403): void
    {
        try { $operation(); $this->fail('Operation should have been rejected.'); }
        catch (HttpException $error) { $this->assertSame($status, $error->getStatusCode()); }
    }

    public function test_roles_are_property_specific_and_pending_or_revoked_members_have_no_access(): void
    {
        $a = User::factory()->create(['role' => 'tenant']);
        $b = User::factory()->create();
        $pa = $this->property($a); $pb = $this->property($b, 'Khu B');
        $access = app(PropertyAccess::class);
        $access->owner($a, $pa); // No global owner role needed to own one's property.
        $this->deny(fn () => $access->owner($a, $pb));
        DB::table('property_memberships')->insert(['property_id' => $pb, 'user_id' => $a->id, 'role' => 'member', 'status' => 'pending']);
        $this->deny(fn () => $access->member($a, $pb));
        DB::table('property_memberships')->where('property_id', $pb)->where('user_id', $a->id)->update(['status' => 'approved']);
        $access->member($a, $pb);
        $this->deny(fn () => $access->owner($a, $pb));
        $this->deny(fn () => $access->tenantId($a, $pb));
        $this->assertCount(2, app(PropertyWorkspaces::class)->visibleTo($a));
        DB::table('property_memberships')->where('property_id', $pb)->where('user_id', $a->id)->update(['revoked_at' => now()]);
        $this->deny(fn () => $access->member($a, $pb));
        $this->assertCount(1, app(PropertyWorkspaces::class)->visibleTo($a));
        DB::table('users')->where('id', $a->id)->update(['is_active' => false]);
        $this->deny(fn () => $access->owner($a, $pa));
    }

    public function test_scoped_queries_exclude_other_properties_and_inconsistent_cross_property_relations(): void
    {
        $a = User::factory()->create(); $b = User::factory()->create();
        $pa = $this->property($a); $pb = $this->property($b);
        $ra = $this->room($pa, 'A101'); $rb = $this->room($pb, 'B101');
        $ga = DB::table('rental_groups')->insertGetId(['property_id' => $pa, 'group_code' => 'GA', 'created_at' => now()]);
        $gb = DB::table('rental_groups')->insertGetId(['property_id' => $pb, 'group_code' => 'GB', 'created_at' => now()]);
        DB::table('occupancies')->insert(['room_id' => $ra, 'rental_group_id' => $ga, 'started_at' => '2026-01-01', 'monthly_rent' => 2800000]);
        DB::table('occupancies')->insert(['room_id' => $rb, 'rental_group_id' => $gb, 'started_at' => '2026-01-01', 'monthly_rent' => 2800000]);
        // Deliberately corrupt a completed record: scope must require both parents in the same property.
        DB::table('occupancies')->insert(['room_id' => $ra, 'rental_group_id' => $gb, 'started_at' => '2025-01-01', 'ended_at' => '2025-02-01', 'monthly_rent' => 2800000]);
        $queries = app(PropertyQueries::class);
        $this->assertSame([$ra], $queries->owner($a, $pa, 'rooms')->pluck('id')->all());
        $this->assertSame(1, $queries->owner($a, $pa, 'occupancies')->count());
        $this->deny(fn () => $queries->owner($a, $pb, 'rooms'));
        $this->expectException(\InvalidArgumentException::class);
        $queries->owner($a, $pa, 'users');
    }

    public function test_linking_requires_same_property_and_cannot_reassign_another_accounts_tenant(): void
    {
        $owner = User::factory()->create(); $otherOwner = User::factory()->create();
        $member = User::factory()->create(['role' => 'tenant']); $another = User::factory()->create(['role' => 'tenant']);
        $pa = $this->property($owner); $pb = $this->property($otherOwner);
        foreach ([$member, $another] as $user) DB::table('property_memberships')->insert(['property_id' => $pa, 'user_id' => $user->id, 'role' => 'member', 'status' => 'approved']);
        $ta = DB::table('tenants')->insertGetId(['property_id' => $pa, 'full_name' => 'Người A', 'phone_number' => '0900000000']);
        $tb = DB::table('tenants')->insertGetId(['property_id' => $pb, 'full_name' => 'Người B', 'phone_number' => '0900000000']);
        $service = app(PropertyWorkspaces::class);
        $this->deny(fn () => $service->linkTenant($owner, $pa, $member->id, $tb), 404);
        $service->linkTenant($owner, $pa, $member->id, $ta);
        $this->assertSame($ta, app(PropertyAccess::class)->tenantId($member, $pa));
        $this->deny(fn () => $service->linkTenant($owner, $pa, $another->id, $ta), 409);
        $this->deny(fn () => $service->linkTenant($otherOwner, $pa, $another->id, $ta));
        $this->assertNull($member->fresh()->tenant_id); // Legacy global binding is not overwritten.
    }

    public function test_client_cannot_forge_property_owner(): void
    {
        $actor = User::factory()->create(); $other = User::factory()->create();
        try { app(PropertyWorkspaces::class)->create($actor, ['name' => 'A', 'address' => 'B', 'owner_user_id' => $other->id]);
            $this->fail('Forged owner accepted'); }
        catch (\Illuminate\Validation\ValidationException) { $this->assertSame(0, DB::table('rental_properties')->count()); }
    }

    public function test_backfill_dry_run_then_apply_preserves_data_and_is_repeatable(): void
    {
        $owner = User::factory()->create();
        DB::table('rental_properties')->insert(['id' => 1, 'name' => 'Khu cũ', 'address' => 'Địa chỉ cũ', 'updated_by' => $owner->id]);
        DB::table('rooms')->insert(['room_number' => 'A101', 'capacity' => 2, 'default_monthly_rent' => 2800000, 'operational_status' => 'available']);
        $tenantId = DB::table('tenants')->insertGetId(['full_name' => 'Người cũ', 'phone_number' => '0900000000']);
        $tenant = User::factory()->create(['role' => 'tenant', 'tenant_id' => $tenantId]);
        $this->artisan('rental:prepare-property-ownership', ['--owner' => $owner->id])->assertSuccessful();
        $this->assertNull(DB::table('rooms')->value('property_id'));
        $this->assertSame(0, DB::table('property_memberships')->count());
        $this->artisan('rental:prepare-property-ownership', ['--owner' => $owner->id, '--apply' => true])->assertFailed();
        $before = DB::table('rooms')->first();
        $args = ['--owner' => $owner->id, '--apply' => true, '--backup-reference' => 'isolated-test-fixture'];
        $this->artisan('rental:prepare-property-ownership', $args)->assertSuccessful();
        $after = DB::table('rooms')->first();
        $this->assertSame($before->default_monthly_rent, $after->default_monthly_rent);
        $this->assertSame($before->id, $after->id);
        $this->assertSame(1, $after->property_id);
        $this->assertSame($tenantId, app(PropertyAccess::class)->tenantId($tenant, 1));
        $this->artisan('rental:prepare-property-ownership', $args)->assertSuccessful();
        $this->assertSame(1, DB::table('property_migration_runs')->count());
        $this->assertSame(2, DB::table('property_memberships')->count());
    }

    public function test_backfill_rejects_ambiguous_multiple_properties_without_writes(): void
    {
        $a = User::factory()->create(); $b = User::factory()->create();
        $this->property($a); $this->property($b);
        $this->artisan('rental:prepare-property-ownership', ['--owner' => $a->id, '--apply' => true, '--backup-reference' => 'test'])->assertFailed();
        $this->assertSame(0, DB::table('property_migration_runs')->count());
    }

    public function test_rollback_refuses_to_discard_established_ownership(): void
    {
        $this->property(User::factory()->create());
        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2026_11_15_000001_add_property_ownership_foundation.php'))->down();
    }
    public function test_empty_migration_can_be_rolled_back_without_losing_the_room_report(): void
    {
        (require database_path('migrations/2026_11_15_000001_add_property_ownership_foundation.php'))->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('rooms', 'property_id'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('property_memberships'));
        $this->assertSame(0, DB::table('room_occupancy_summary')->count());
    }

    public function test_completed_backfill_rejects_new_unassigned_records(): void
    {
        $owner = User::factory()->create();
        DB::table('rental_properties')->insert(['id' => 1, 'name' => 'Legacy', 'address' => 'A', 'updated_by' => $owner->id]);
        $args = ['--owner' => $owner->id, '--apply' => true, '--backup-reference' => 'isolated-test-fixture'];
        $this->artisan('rental:prepare-property-ownership', $args)->assertSuccessful();
        DB::table('rooms')->insert(['room_number' => 'LATE', 'capacity' => 2, 'default_monthly_rent' => 2000, 'operational_status' => 'available']);
        $this->artisan('rental:prepare-property-ownership', $args)->assertFailed();
        $this->assertNull(DB::table('rooms')->where('room_number', 'LATE')->value('property_id'));
        $this->assertSame(1, DB::table('property_migration_runs')->count());
    }

    public function test_tenant_binding_is_rechecked_against_the_property(): void
    {
        $owner = User::factory()->create(); $other = User::factory()->create();
        $member = User::factory()->create(['role' => 'tenant']);
        $a = $this->property($owner); $b = $this->property($other);
        $tenant = DB::table('tenants')->insertGetId(['property_id' => $b, 'full_name' => 'Other tenant', 'phone_number' => '0900000000']);
        DB::table('property_memberships')->insert(['property_id' => $a, 'user_id' => $member->id, 'role' => 'member', 'status' => 'approved', 'tenant_id' => $tenant]);
        $this->deny(fn () => app(PropertyAccess::class)->tenantId($member, $a));
    }

    public function test_invoice_payment_and_photo_reads_follow_property_ownership(): void
    {
        // Minimal dependent tables isolate the traversal; business rules have their own suites.
        foreach (['invoices' => 'occupancy_id', 'payments' => 'invoice_id', 'maintenance_requests' => 'occupancy_id', 'maintenance_photos' => 'maintenance_request_id'] as $table => $parent) {
            \Illuminate\Support\Facades\Schema::create($table, function ($blueprint) use ($parent) {
                $blueprint->id(); $blueprint->unsignedBigInteger($parent);
            });
        }
        $a = User::factory()->create(); $b = User::factory()->create();
        $pa = $this->property($a); $pb = $this->property($b);
        $expected = [];
        foreach ([$pa, $pb] as $property) {
            $room = $this->room($property, 'ROOM-'.$property);
            $group = DB::table('rental_groups')->insertGetId(['property_id' => $property, 'group_code' => 'GROUP-'.$property, 'created_at' => now()]);
            $occupancy = DB::table('occupancies')->insertGetId(['room_id' => $room, 'rental_group_id' => $group, 'started_at' => '2026-01-01', 'monthly_rent' => 2000]);
            $invoice = DB::table('invoices')->insertGetId(['occupancy_id' => $occupancy]);
            $payment = DB::table('payments')->insertGetId(['invoice_id' => $invoice]);
            $report = DB::table('maintenance_requests')->insertGetId(['occupancy_id' => $occupancy]);
            $photo = DB::table('maintenance_photos')->insertGetId(['maintenance_request_id' => $report]);
            $expected[$property] = ['invoices' => $invoice, 'payments' => $payment, 'maintenance_photos' => $photo];
        }
        foreach ($expected[$pa] as $table => $id) {
            $this->assertSame([$id], app(PropertyQueries::class)->owner($a, $pa, $table)->pluck('id')->all());
        }
        foreach ($expected[$pb] as $table => $id) {
            $this->assertSame([$id], app(PropertyQueries::class)->owner($b, $pb, $table)->pluck('id')->all());
        }
    }

}
