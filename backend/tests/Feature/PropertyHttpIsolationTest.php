<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PayosGateway;
use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyWorkspaces;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCatalogSchema;
use Tests\TestCase;

class PropertyHttpIsolationTest extends TestCase
{
    use CreatesCatalogSchema;

    private User $a;

    private User $b;

    private int $pa;

    private int $pb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCatalogSchema();
        foreach (['000001_add_property_ownership_foundation', '000002_create_property_payment_settings', '000003_scope_catalog_unique_keys'] as $suffix) {
            (require database_path('migrations/2026_11_15_'.$suffix.'.php'))->up();
        }
        $this->a = User::factory()->create(['role' => 'tenant']);
        $this->b = User::factory()->create();
        $this->pa = app(PropertyWorkspaces::class)->create($this->a, ['name' => 'A', 'address' => 'Address A'])->id;
        $this->pb = app(PropertyWorkspaces::class)->create($this->b, ['name' => 'B', 'address' => 'Address B'])->id;
        config(['tenancy.enabled' => true]);
    }

    private function headers(int $id): array
    {
        return ['X-Property-ID' => (string) $id];
    }

    private function createRoom(User $owner, int $id): int
    {
        $this->actingAs($owner);

        return $this->postJson('/api/owner/rooms', ['room_number' => 'A101', 'capacity' => 2, 'default_monthly_rent' => '2000'], $this->headers($id))->assertCreated()->json('data.id');
    }

    public function test_http_rooms_are_scoped_for_lists_binding_writes_and_duplicate_codes(): void
    {
        $ra = $this->createRoom($this->a, $this->pa);
        $rb = $this->createRoom($this->b, $this->pb);
        $this->actingAs($this->a);
        $this->getJson('/api/owner/rooms', $this->headers($this->pa))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ra);
        $this->getJson('/api/owner/rooms/'.$rb, $this->headers($this->pa))->assertNotFound();
        $this->patchJson('/api/owner/rooms/'.$rb, ['capacity' => 8], $this->headers($this->pa))->assertNotFound();
        $this->deleteJson('/api/owner/rooms/'.$rb, [], $this->headers($this->pa))->assertNotFound();
        $this->getJson('/api/owner/rooms', $this->headers($this->pb))->assertForbidden();
        $this->assertSame(2, DB::table('rooms')->where('id', $rb)->value('capacity'));
        $this->assertSame($this->pa, DB::table('rooms')->where('id', $ra)->value('property_id'));
        $this->assertNull(app(PropertyContext::class)->id());
    }

    public function test_pending_revoked_members_cannot_use_owner_or_tenant_routes(): void
    {
        DB::table('property_memberships')->insert(['property_id' => $this->pb, 'user_id' => $this->a->id, 'status' => 'pending', 'role' => 'owner']);
        $this->actingAs($this->a);
        $this->getJson('/api/tenant/profile', $this->headers($this->pb))->assertForbidden();
        DB::table('property_memberships')->where('property_id', $this->pb)->where('user_id', $this->a->id)->update(['status' => 'approved']);
        // Forging membership.role cannot grant ownership.
        $this->getJson('/api/owner/rooms', $this->headers($this->pb))->assertForbidden();
        $this->getJson('/api/tenant/profile', $this->headers($this->pb))->assertNotFound();
        DB::table('property_memberships')->where('property_id', $this->pb)->where('user_id', $this->a->id)->update(['revoked_at' => now()]);
        $this->getJson('/api/tenant/profile', $this->headers($this->pb))->assertForbidden();
    }

    public function test_selection_and_login_identity_use_property_role_and_do_not_expose_bank_settings(): void
    {
        $this->actingAs($this->a);
        $this->getJson('/api/properties')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.bank_account_number');
        $this->putJson('/api/properties/selected', ['property_id' => $this->pb])->assertForbidden();
        $this->putJson('/api/properties/selected', ['property_id' => $this->pa])->assertOk();
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.role', 'owner')->assertJsonPath('data.property_id', $this->pa);
        $this->getJson('/api/owner/rooms')->assertStatus(409);
        $this->getJson('/api/auth/me', $this->headers($this->pb))->assertOk()
            ->assertJsonPath('data.needs_property', true)->assertJsonPath('data.property_id', null);
        $this->assertSame('tenant', DB::table('users')->where('id', $this->a->id)->value('role'));
    }

    public function test_missing_context_fails_closed_and_cannot_leak_between_requests(): void
    {
        $this->actingAs($this->a);
        $this->getJson('/api/owner/rooms')->assertStatus(409);
        $this->getJson('/api/owner/rooms', $this->headers($this->pa))->assertOk();
        $this->getJson('/api/owner/rooms')->assertStatus(409);
    }

    public function test_other_property_never_inherits_legacy_payment_credentials(): void
    {
        config(['payos.client_id' => 'legacy', 'payos.api_key' => 'legacy', 'payos.checksum_key' => 'legacy', 'payos.enabled' => true]);
        $context = app(PropertyContext::class);
        $this->assertFalse($context->run($this->pa, fn () => app(PayosGateway::class)->configured()));
        DB::table('property_migration_runs')->insert(['migration_key' => 'legacy-property-1', 'property_id' => $this->pa, 'owner_user_id' => $this->a->id, 'backup_reference' => 'test-only', 'report' => '{}', 'completed_at' => now()]);
        $this->assertTrue($context->run($this->pa, fn () => app(PayosGateway::class)->configured()));
        $this->assertFalse($context->run($this->pb, fn () => app(PayosGateway::class)->configured()));
    }
}
