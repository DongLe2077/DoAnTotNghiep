<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ScheduledDeletions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class ScheduledDeletionTest extends TestCase
{
    use CreatesCashBookSchema;

    public function test_a_tenant_with_history_can_be_queued_cancelled_and_deleted_after_three_days(): void
    {
        $this->travelTo(now()->setDate(2026, 5, 7)->setTime(9, 30));
        $scheduledAt = now()->toImmutable();
        $this->createCashBookSchema();
        (require database_path('migrations/2026_11_13_000001_create_scheduled_deletions.php'))->up();
        $this->actingAs(User::factory()->create(), 'web');

        $tenant = $this->postJson('/api/owner/tenants', ['full_name' => 'Người thuê lịch sử', 'phone_number' => '0900123123'])
            ->assertCreated()->json('data.id');
        $this->putJson("/api/owner/tenants/{$tenant}/account", [
            'username' => 'removal_test', 'password' => 'Test-Rentbase!', 'password_confirmation' => 'Test-Rentbase!', 'is_active' => true,
        ])->assertOk();
        $room = $this->postJson('/api/owner/rooms', ['room_number' => 'REMOVE-01', 'capacity' => 1, 'default_monthly_rent' => '2000'])
            ->assertCreated()->json('data.id');
        $group = $this->postJson('/api/owner/rental-groups', ['group_code' => 'REMOVE-GROUP'])->assertCreated()->json('data.id');
        $stay = $this->postJson('/api/owner/occupancies', [
            'room_id' => $room, 'rental_group_id' => $group, 'tenant_ids' => [$tenant],
            'representative_tenant_id' => $tenant, 'started_at' => '2026-04-01 00:00:00',
        ])->assertCreated()->json('data.id');
        $billing = $this->postJson("/api/owner/occupancies/{$stay}/invoices/preview", ['billing_month' => '2026-04'])
            ->assertOk()->json('data.preview_token');
        $invoice = $this->postJson("/api/owner/occupancies/{$stay}/invoices", [
            'billing_month' => '2026-04', 'preview_token' => $billing,
        ])->assertCreated()->json('data.id');

        $target = "/api/owner/scheduled-deletions/tenants/{$tenant}";
        $queue = '/api/owner/scheduled-deletions';
        $preview = $this->getJson($target.'/preview')->assertOk()->json('data');
        $this->assertContains(['label' => 'Hóa đơn', 'count' => 1], $preview['impact']);
        $job = $this->postJson($target, ['confirmation' => $preview['confirmation']])->assertAccepted()->json('data.id');
        $this->getJson($queue)->assertOk()->assertJsonPath('data.0.id', $job);
        $deletions = app(ScheduledDeletions::class);
        $this->assertTrue($deletions->anyCoversInvoice($invoice));

        $this->travelTo($scheduledAt->addDays(3)->subSecond());
        $this->assertSame(0, $deletions->run());
        $this->assertDatabaseHas('invoices', ['id' => $invoice]);
        $this->deleteJson("{$queue}/{$job}")->assertNoContent();
        $this->assertFalse($deletions->anyCoversInvoice($invoice));
        $this->travelTo($scheduledAt->addDays(3));
        $this->assertSame(0, $deletions->run());
        $this->assertDatabaseHas('tenants', ['id' => $tenant]);

        $this->postJson($target, ['confirmation' => $preview['confirmation']])->assertAccepted();
        $this->travelTo($scheduledAt->addDays(6));
        $this->artisan('rental:purge-scheduled-deletions')->assertSuccessful();
        $this->assertDatabaseMissing('tenants', ['id' => $tenant]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice]);
        $this->assertDatabaseMissing('occupancies', ['id' => $stay]);
        $this->assertDatabaseHas('rooms', ['id' => $room]);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_confirmed_immediate_deletion_is_property_scoped_and_safe_to_retry(): void
    {
        $this->createCashBookSchema();
        foreach ([
            '2026_11_13_000001_create_scheduled_deletions',
            '2026_11_15_000001_add_property_ownership_foundation',
            '2026_11_23_000002_create_scheduled_deletion_receipts',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        config(['tenancy.enabled' => true]);
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $workspaces = app(\App\Services\Tenancy\PropertyWorkspaces::class);
        $property = $workspaces->create($owner, ['name' => 'Khu A', 'address' => 'Địa chỉ A']);
        $other = $workspaces->create($otherOwner, ['name' => 'Khu B', 'address' => 'Địa chỉ B']);
        $headers = ['X-Property-ID' => (string) $property->id];
        $otherHeaders = ['X-Property-ID' => (string) $other->id];
        $queue = '/api/owner/scheduled-deletions';
        $room = $this->actingAs($owner)->postJson('/api/owner/rooms', [
            'room_number' => 'REMOVE-NOW', 'capacity' => 1, 'default_monthly_rent' => '2000',
        ], $headers)->assertCreated()->json('data.id');
        $target = "{$queue}/rooms/{$room}";
        $scheduledConfirmation = $this->getJson($target.'/preview', $headers)->assertOk()->json('data.confirmation');
        $firstJob = $this->postJson($target, ['confirmation' => $scheduledConfirmation], $headers)->assertAccepted()->json('data.id');
        $firstPurge = "{$queue}/{$firstJob}/purge";
        $firstConfirmation = $this->getJson($firstPurge.'/preview', $headers)->assertOk()
            ->assertJsonPath('data.impact.0', ['label' => 'Phòng', 'count' => 1])->json('data.confirmation');
        $this->deleteJson("{$queue}/{$firstJob}", [], $headers)->assertNoContent();
        $this->postJson($firstPurge, ['confirmation' => $firstConfirmation], $headers)->assertNotFound();
        $this->assertDatabaseHas('rooms', ['id' => $room]);

        $job = $this->postJson($target, ['confirmation' => $scheduledConfirmation], $headers)->assertAccepted()->json('data.id');
        $purge = "{$queue}/{$job}/purge";
        $confirmation = $this->getJson($purge.'/preview', $headers)->assertOk()->json('data.confirmation');
        $this->getJson($purge.'/preview', $otherHeaders)->assertForbidden();
        $this->actingAs($otherOwner)->getJson($purge.'/preview', $otherHeaders)->assertNotFound();
        $this->postJson($purge, ['confirmation' => $confirmation], $otherHeaders)->assertNotFound();
        $this->actingAs($owner)->postJson($purge, [], $headers)->assertUnprocessable();
        $this->postJson($purge, ['confirmation' => $scheduledConfirmation], $headers)->assertConflict();
        $this->postJson($purge, ['confirmation' => $firstConfirmation], $headers)->assertConflict();
        $this->assertDatabaseHas('rooms', ['id' => $room]);

        $this->postJson($purge, ['confirmation' => $confirmation], $headers)->assertNoContent();
        $this->postJson($purge, ['confirmation' => $confirmation], $headers)->assertNoContent();
        $this->getJson($queue, $headers)->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($otherOwner)->postJson($purge, ['confirmation' => $confirmation], $otherHeaders)->assertNotFound();
        $this->assertDatabaseMissing('rooms', ['id' => $room]);
        $this->assertDatabaseMissing('scheduled_deletions', ['id' => $job]);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_permanent_deletion_rechecks_payos_after_confirmation(): void
    {
        $this->travelTo(now()->setDate(2026, 5, 7)->setTime(9, 30));
        $this->createCashBookSchema();
        foreach (['2026_11_09_000001_create_payos_checkout_tables', '2026_11_13_000001_create_scheduled_deletions'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        $this->actingAs(User::factory()->create());
        $tenant = $this->postJson('/api/owner/tenants', ['full_name' => 'Người thuê thanh toán', 'phone_number' => '0900123123'])
            ->assertCreated()->json('data.id');
        $this->putJson("/api/owner/tenants/{$tenant}/account", [
            'username' => 'paying_test', 'password' => 'Test-Rentbase!', 'password_confirmation' => 'Test-Rentbase!', 'is_active' => true,
        ])->assertOk();
        $room = $this->postJson('/api/owner/rooms', ['room_number' => 'PAYING', 'capacity' => 1, 'default_monthly_rent' => '2000'])
            ->assertCreated()->json('data.id');
        $group = $this->postJson('/api/owner/rental-groups', ['group_code' => 'PAYING-GROUP'])->assertCreated()->json('data.id');
        $stay = $this->postJson('/api/owner/occupancies', [
            'room_id' => $room, 'rental_group_id' => $group, 'tenant_ids' => [$tenant],
            'representative_tenant_id' => $tenant, 'started_at' => '2026-04-01 00:00:00',
        ])->assertCreated()->json('data.id');
        $billing = $this->postJson("/api/owner/occupancies/{$stay}/invoices/preview", ['billing_month' => '2026-04'])
            ->assertOk()->json('data.preview_token');
        $invoice = $this->postJson("/api/owner/occupancies/{$stay}/invoices", [
            'billing_month' => '2026-04', 'preview_token' => $billing,
        ])->assertCreated()->json('data.id');

        $checkout = DB::table('invoice_payos_checkouts')->insertGetId([
            'invoice_id' => $invoice, 'order_code' => 12345678901234, 'amount' => 2000,
            'invoice_version' => str_repeat('a', 64), 'status' => 'paid', 'expires_at' => now()->addDays(4),
        ]);
        $queue = '/api/owner/scheduled-deletions';
        $target = "{$queue}/invoices/{$invoice}";
        $preview = $this->getJson($target.'/preview')->assertOk()->json('data.confirmation');
        $job = $this->postJson($target, ['confirmation' => $preview])->assertAccepted()->json('data.id');
        $purge = "{$queue}/{$job}/purge";
        $confirmation = $this->getJson($purge.'/preview')->assertOk()->json('data.confirmation');

        DB::table('invoice_payos_checkouts')->where('id', $checkout)->update(['status' => 'pending']);
        $this->getJson($purge.'/preview')->assertConflict();
        $this->postJson($purge, ['confirmation' => $confirmation])->assertConflict();
        $this->travel(3)->days();
        $this->artisan('rental:purge-scheduled-deletions')->assertSuccessful();
        $this->assertDatabaseHas('invoices', ['id' => $invoice]);
        $this->assertDatabaseHas('scheduled_deletions', ['id' => $job, 'problem' => null]);
        $this->deleteJson("{$queue}/{$job}")->assertNoContent();
    }
}
