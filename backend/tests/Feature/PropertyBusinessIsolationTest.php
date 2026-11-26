<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PayosGateway;
use App\Services\Tenancy\PropertyWorkspaces;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PayOS\Crypto\CryptoProvider;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class PropertyBusinessIsolationTest extends TestCase
{
    use CreatesCashBookSchema;

    private User $member;

    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(9, 0));
        $this->createCashBookSchema();
        foreach (['2026_11_09_000001_create_payos_checkout_tables', '2026_11_13_000001_create_scheduled_deletions', '2026_11_15_000001_add_property_ownership_foundation', '2026_11_15_000002_create_property_payment_settings', '2026_11_15_000003_scope_catalog_unique_keys', '2026_11_23_000003_create_checkout_requests'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        config(['tenancy.enabled' => true, 'app.url' => 'https://rentbase.example.test']);
        $this->member = User::factory()->create(['role' => 'owner']);
        $this->a = $this->fixture('A');
        $this->b = $this->fixture('B');
    }

    private function fixture(string $code): array
    {
        $owner = User::factory()->create();
        $property = app(PropertyWorkspaces::class)->create($owner, ['name' => $code, 'address' => 'Address '.$code])->id;
        $headers = ['X-Property-ID' => (string) $property];
        $this->actingAs($owner);
        $tenant = $this->postJson('/api/owner/tenants', ['full_name' => 'Tenant '.$code, 'phone_number' => '0900000000'], $headers)->assertCreated()->json('data.id');
        DB::table('property_memberships')->insert(['property_id' => $property, 'user_id' => $this->member->id, 'tenant_id' => $tenant, 'role' => 'member', 'status' => 'approved']);
        $room = $this->postJson('/api/owner/rooms', ['room_number' => 'A101', 'capacity' => 2, 'default_monthly_rent' => '2000'], $headers)->assertCreated()->json('data.id');
        $group = $this->postJson('/api/owner/rental-groups', ['group_code' => 'G1'], $headers)->assertCreated()->json('data.id');
        $occupancy = $this->postJson('/api/owner/occupancies', ['room_id' => $room, 'rental_group_id' => $group, 'tenant_ids' => [$tenant], 'representative_tenant_id' => $tenant, 'started_at' => '2026-10-01 00:00:00'], $headers)->assertCreated()->json('data.id');
        $preview = $this->postJson('/api/owner/occupancies/'.$occupancy.'/invoices/preview', ['billing_month' => '2026-10'], $headers)->assertOk()->json('data.preview_token');
        $invoice = $this->postJson('/api/owner/occupancies/'.$occupancy.'/invoices', ['billing_month' => '2026-10', 'preview_token' => $preview], $headers)->assertCreated()->json('data.id');

        return compact('owner', 'property', 'headers', 'tenant', 'room', 'group', 'occupancy', 'invoice');
    }

    public function test_owner_and_shared_member_only_see_selected_property_invoices_and_reports(): void
    {
        foreach ([$this->a, $this->b] as $fixture) {
            $this->actingAs($fixture['owner']);
            $this->getJson('/api/owner/invoices', $fixture['headers'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $fixture['invoice']);
            $this->getJson('/api/owner/dashboard?month=2026-10', $fixture['headers'])->assertOk()->assertJsonPath('data.rooms.total_count', 1)->assertJsonPath('data.tenants.residing_count', 1);
            $this->actingAs($this->member);
            $this->getJson('/api/tenant/invoices', $fixture['headers'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $fixture['invoice']);
            $this->getJson('/api/tenant/current-stay', $fixture['headers'])->assertOk();
        }
        $this->getJson('/api/tenant/invoices/'.$this->b['invoice'], $this->a['headers'])->assertNotFound();
        $this->getJson('/api/tenant/invoices/'.$this->b['invoice'].'/pdf', $this->a['headers'])->assertNotFound();
        $this->actingAs($this->a['owner']);
        $this->postJson('/api/owner/invoices/'.$this->b['invoice'].'/payments', ['amount' => '2000', 'paid_at' => now()->toDateTimeString(), 'payment_method' => 'cash', 'idempotency_key' => 'foreign-test'], $this->a['headers'])->assertNotFound();
        $this->putJson('/api/owner/tenants/'.$this->a['tenant'].'/account', ['username' => 'overwrite'], $this->a['headers'])->assertStatus(409);
    }

    public function test_relationships_cannot_point_to_other_property_and_global_rate_stays_local(): void
    {
        $this->actingAs($this->a['owner']);
        $this->postJson('/api/owner/occupancies', ['room_id' => $this->b['room'], 'rental_group_id' => $this->a['group'], 'tenant_ids' => [$this->a['tenant']], 'started_at' => '2026-11-01 00:00:00'], $this->a['headers'])->assertUnprocessable();
        $this->putJson('/api/owner/rooms/rental-rate', ['default_monthly_rent' => '3000'], $this->a['headers'])->assertOk();
        $this->assertEquals(2000, DB::table('rooms')->where('id', $this->b['room'])->value('default_monthly_rent'));
        $this->assertEquals(3000, DB::table('rooms')->where('id', $this->a['room'])->value('default_monthly_rent'));
    }

    public function test_deleting_local_tenant_preserves_shared_account_and_other_property_history(): void
    {
        $this->actingAs($this->a['owner']);
        $path = '/api/owner/scheduled-deletions/tenants/'.$this->a['tenant'];
        $preview = $this->getJson($path.'/preview', $this->a['headers'])->assertOk()->json('data.confirmation');
        $job = $this->postJson($path, ['confirmation' => $preview], $this->a['headers'])->assertAccepted()->json('data.id');
        $this->actingAs($this->b['owner']);
        $this->deleteJson('/api/owner/scheduled-deletions/'.$job, [], $this->b['headers'])->assertNotFound();
        $this->travel(3)->days();
        $this->artisan('rental:purge-scheduled-deletions')->assertSuccessful();
        $this->assertDatabaseMissing('tenants', ['id' => $this->a['tenant']]);
        $this->assertDatabaseHas('users', ['id' => $this->member->id, 'is_active' => true]);
        $this->assertDatabaseHas('invoices', ['id' => $this->b['invoice']]);
        $this->assertDatabaseHas('property_memberships', ['property_id' => $this->b['property'], 'tenant_id' => $this->b['tenant'], 'user_id' => $this->member->id]);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_signed_webhook_only_settles_its_property_and_retries_are_idempotent(): void
    {
        foreach ([$this->a, $this->b] as $f) {
            DB::table('property_payment_settings')->insert(['property_id' => $f['property'], 'enabled' => true, 'credentials' => Crypt::encryptString(json_encode(['client_id' => 'test', 'api_key' => 'test', 'checksum_key' => 'key-'.$f['property']]))]);
        }
        $this->partialMock(PayosGateway::class)->shouldReceive('create')->once()->andReturnUsing(fn ($request) => ['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'paymentLinkId' => '0123456789abcdef0123456789abcdef', 'checkoutUrl' => 'https://pay.payos.vn/web/0123456789abcdef0123456789abcdef']);
        $this->actingAs($this->member)->postJson('/api/tenant/invoices/'.$this->b['invoice'].'/payos', [], $this->b['headers'])->assertOk();
        $checkout = DB::table('invoice_payos_checkouts')->where('invoice_id', $this->b['invoice'])->first();
        $data = ['code' => '00', 'currency' => 'VND', 'orderCode' => (int) $checkout->order_code, 'amount' => 2000, 'paymentLinkId' => $checkout->link_id, 'reference' => 'TEST-BANK-1', 'transactionDateTime' => now()->toDateTimeString()];
        $signed = ['data' => $data, 'signature' => (new CryptoProvider)->createSignatureFromObj($data, 'key-'.$this->b['property'])];
        $this->postJson('/api/webhooks/payos/'.$this->a['property'], $signed)->assertStatus(400);
        $this->assertDatabaseCount('payments', 0);
        $this->postJson('/api/webhooks/payos/'.$this->b['property'], $signed)->assertNoContent();
        $this->postJson('/api/webhooks/payos/'.$this->b['property'], $signed)->assertNoContent();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['invoice_id' => $this->b['invoice'], 'amount' => 2000]);
        $this->getJson('/api/tenant/invoices/'.$this->b['invoice'], $this->b['headers'])->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->getJson('/api/tenant/invoices/'.$this->a['invoice'], $this->a['headers'])->assertOk()->assertJsonPath('data.payment_status', 'unpaid');
    }

    public function test_photos_and_notifications_are_private_to_the_selected_property(): void
    {
        Storage::fake('maintenance');
        $this->actingAs($this->member);
        $report = $this->postJson('/api/tenant/occupancies/'.$this->b['occupancy'].'/maintenance-requests', ['title' => 'Vòi nước bị rò', 'description' => 'Cần kiểm tra vòi nước trong phòng'], $this->b['headers'])->assertCreated()->json('data.id');
        $photo = $this->post('/api/tenant/maintenance-requests/'.$report.'/photos', ['photos' => [UploadedFile::fake()->image('water.jpg')]], $this->b['headers'] + ['Accept' => 'application/json'])->assertOk()->json('data.0.id');
        $this->getJson('/api/tenant/maintenance-requests/'.$report.'/photos/'.$photo, $this->a['headers'])->assertNotFound();
        $this->actingAs($this->a['owner']);
        $this->getJson('/api/owner/maintenance-requests/'.$report.'/photos/'.$photo, $this->a['headers'])->assertNotFound();
        $this->getJson('/api/notifications', $this->a['headers'])->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->b['owner']);
        $this->getJson('/api/owner/maintenance-requests/'.$report.'/photos/'.$photo, $this->b['headers'])->assertOk();
        $this->getJson('/api/notifications', $this->b['headers'])->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_reminder_job_runs_each_property_without_duplicate_or_foreign_notifications(): void
    {
        DB::table('invoices')->update(['due_date' => '2026-11-01']);
        $this->artisan('rental:remind-invoices', ['--overdue' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('app_notifications', 0);
        $this->artisan('rental:remind-invoices', ['--overdue' => true])->assertSuccessful();
        $this->artisan('rental:remind-invoices', ['--overdue' => true])->assertSuccessful();
        $this->assertDatabaseCount('app_notifications', 2);
        $this->actingAs($this->member);
        foreach ([$this->a, $this->b] as $fixture) {
            $this->getJson('/api/notifications', $fixture['headers'])->assertOk()->assertJsonCount(1, 'data');
        }
    }

    public function test_audit_detects_cross_property_graph_without_rewriting_records(): void
    {
        $this->artisan('rental:audit-property-ownership')->assertSuccessful();
        DB::table('rental_groups')->where('id', $this->a['group'])->update(['group_code' => 'BROKEN', 'property_id' => $this->b['property']]);
        $this->artisan('rental:audit-property-ownership')->assertFailed();
        $this->assertDatabaseHas('rental_groups',['id' => $this->a['group'], 'property_id' => $this->b['property']]);
    }
}
