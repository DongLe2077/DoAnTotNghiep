<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tenancy\PropertyWorkspaces;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class PropertyOnboardingTest extends TestCase
{
    use CreatesCashBookSchema;

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
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        config(['tenancy.enabled' => true]);
        $this->owner = User::factory()->create();
        $this->otherOwner = User::factory()->create();
        $this->property = app(PropertyWorkspaces::class)->create($this->owner, ['name' => 'Khu A', 'address' => 'Đường A'])->id;
        $this->otherProperty = app(PropertyWorkspaces::class)->create($this->otherOwner, ['name' => 'Khu B', 'address' => 'Đường B'])->id;
    }

    public function test_registration_never_inherits_a_profile_by_phone_or_accepts_a_role_from_the_client(): void
    {
        $this->actingAs($this->owner);
        $tenant = $this->postJson('/api/owner/tenants', [
            'full_name' => 'Hồ sơ có sẵn', 'phone_number' => '0900000000',
        ], $this->scope())->assertCreated()->json('data.id');
        Auth::guard('web')->logout();
        $payload = [
            'full_name' => 'Người mới', 'username' => 'NewUser', 'contact_phone' => '0900000000',
            'password' => 'Fixture@2026', 'password_confirmation' => 'Fixture@2026',
        ];
        $this->postJson('/api/auth/registration', $payload + ['role' => 'owner', 'tenant_id' => $tenant])
            ->assertUnprocessable()->assertJsonValidationErrors(['role', 'tenant_id']);
        $created = $this->postJson('/api/auth/registration', $payload)->assertCreated()
            ->assertJsonPath('data.needs_property', true)->assertJsonPath('data.tenant_id', null)
            ->assertJsonPath('data.username', 'newuser')->assertJsonMissingPath('data.password_hash');
        $userId = $created->json('data.id');
        $welcome = $this->getJson('/api/notifications?type=welcome')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.data.title', 'Chào mừng bạn đến với RentBase')->json('data.0.id');
        $this->assertDatabaseHas('app_notifications', ['id' => $welcome, 'user_id' => $userId, 'type' => 'welcome']);
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->patchJson('/api/notifications/'.$welcome.'/read')->assertOk()->assertJsonPath('data.is_read', true);
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->assertDatabaseMissing('property_memberships', ['user_id' => $userId]);
        $this->assertTrue(Hash::check('Fixture@2026', User::findOrFail($userId)->password_hash));
        $this->getJson('/api/owner/rooms', $this->scope())->assertForbidden();
        $this->postJson('/api/properties', ['name' => 'Khu không được tạo', 'address' => 'Địa chỉ thử'])->assertForbidden();
        $this->assertDatabaseMissing('rental_properties', ['owner_user_id' => $userId]);
        $this->postJson('/api/auth/registration', $payload)->assertConflict();
        Auth::guard('web')->logout();
        $this->postJson('/api/auth/registration', $payload)->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/api/auth/login', ['username' => 'newuser', 'password' => 'Fixture@2026'])->assertOk();
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $welcome);

        DB::table('property_memberships')->insert(['property_id' => $this->property, 'user_id' => $userId,
            'role' => 'tenant', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/notifications?type=welcome', $this->scope())->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $welcome)->assertJsonPath('data.0.is_read', true)
            ->assertJsonPath('data.0.data.body', 'Chào Người mới! Bạn có thể xem thông tin ở Phòng của tôi và theo dõi các khoản cần thanh toán trong Hóa đơn.');
    }

    public function test_owner_creation_keeps_contact_and_photo_private_until_membership_is_approved(): void
    {
        Storage::fake('local');
        $newOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $guest = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($newOwner);
        $id = $this->post('/api/properties', [
            'name' => 'Khu mới', 'address' => 'Địa chỉ mới', 'contact_name' => 'Chủ khu',
            'phone' => '0900000002', 'email' => 'contact@example.test',
            'photo' => UploadedFile::fake()->image('room.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.role', 'owner')->json('data.id');
        $this->assertDatabaseHas('property_memberships', ['property_id' => $id, 'user_id' => $newOwner->id, 'role' => 'owner']);
        $path = DB::table('rental_properties')->where('id', $id)->value('photo_path');
        Storage::disk('local')->assertExists($path);
        $this->get('/api/properties/photo', $this->scope($id))->assertOk();
        $this->actingAs($guest)->getJson('/api/properties/photo', $this->scope($id))->assertForbidden();
        $this->getJson('/api/tenant/workspace', $this->scope($id))->assertForbidden();
    }

    public function test_preview_pending_approval_and_explicit_profile_binding(): void
    {
        $invite = $this->invite();
        $code = $invite['code'];
        $persisted = DB::table('property_invitations')->find($invite['id']);
        $this->assertSame(hash('sha256', str_replace('-', '', $code)), $persisted->code_hash);
        $this->getJson('/api/owner/invitations', $this->scope())->assertOk()->assertJsonMissingPath('data.0.code');
        $user = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($user);
        $this->postJson('/api/properties/join-preview', ['code' => strtolower(str_replace('-', ' ', $code))])
            ->assertOk()->assertExactJson(['data' => ['property_name' => 'Khu A']]);
        $id = $this->join($code);
        $this->postJson('/api/properties/join-requests', ['code' => $code])->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('property_join_requests', 1);
        $this->putJson('/api/properties/selected', ['property_id' => $this->property])->assertForbidden();
        $this->getJson('/api/tenant/invoices', $this->scope())->assertForbidden();
        $this->actingAs($this->otherOwner)
            ->putJson('/api/owner/join-requests/'.$id, ['decision' => 'approved'], $this->scope($this->otherProperty))->assertNotFound();
        $this->actingAs($this->owner);
        $this->getJson('/api/owner/join-requests', $this->scope())->assertOk()->assertJsonPath('pending_count', 1);
        $this->decide($id)->assertOk()->assertJsonPath('data.tenant_id', null);
        $this->decide($id)->assertOk();
        $this->assertDatabaseHas('property_invitations', ['id' => $invite['id'], 'approvals' => 1]);
        $this->assertDatabaseCount('invoice_access_grants', 0);

        $this->actingAs($user);
        $this->getJson('/api/properties/join-requests')->assertOk()->assertJsonPath('data.0.status', 'approved');
        $this->getJson('/api/tenant/workspace', $this->scope())->assertOk()->assertJsonPath('data.rental_status', 'unlinked');
        $this->getJson('/api/tenant/invoices', $this->scope())->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/owner/rooms', $this->scope())->assertForbidden();
        $this->postJson('/api/properties', ['name' => 'Khu không được tạo', 'address' => 'Địa chỉ thử'])->assertForbidden();

        $this->actingAs($this->owner);
        $profile = $this->postJson('/api/owner/tenants', [
            'full_name' => 'Người vừa duyệt', 'phone_number' => '0900000000',
        ], $this->scope())->assertCreated()->json('data.id');
        $this->putJson('/api/properties/tenant-link', ['user_id' => $user->id, 'tenant_id' => $profile], $this->scope())->assertNoContent();
        $this->actingAs($user)->getJson('/api/tenant/workspace', $this->scope())->assertOk()->assertJsonPath('data.rental_status', 'no_current_stay');
        $this->getJson('/api/tenant/profile', $this->scope())->assertOk()->assertJsonPath('data.id', $profile);
        $this->getJson('/api/tenant/invoices', $this->scope())->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rotation_rechecks_pending_code_and_new_code_repairs_the_same_request(): void
    {
        $invite = $this->invite();
        $member = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($member);
        $id = $this->join($invite['code']);
        $this->actingAs($this->owner);
        $replacement = $this->postJson('/api/owner/invitations/'.$invite['id'].'/rotate', [], $this->scope())->assertCreated()->json('data');
        $this->decide($id)->assertConflict()->assertJsonPath('message', 'Mã mời đã bị thu hồi. Hãy xin mã mới và gửi lại yêu cầu.');
        $this->actingAs($member);
        $this->postJson('/api/properties/join-preview', ['code' => $invite['code']])->assertUnprocessable();
        $this->postJson('/api/properties/join-requests', ['code' => $replacement['code']])->assertOk()->assertJsonPath('data.id', $id);
        $this->actingAs($this->owner);
        $this->decide($id)->assertOk();
        $this->assertDatabaseHas('property_invitations', ['id' => $replacement['id'], 'approvals' => 1]);
        $this->assertDatabaseHas('property_invitations', ['id' => $invite['id'], 'approvals' => 0]);
    }

    public function test_expiry_withdrawal_rejection_and_last_slot_do_not_grant_access(): void
    {
        $expired = $this->invite(['days' => 1]);
        $limited = $this->invite(['max_approvals' => 1]);
        $first = User::factory()->create(['role' => 'tenant']);
        $second = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($first);
        $firstId = $this->join($limited['code']);
        $expiredId = $this->joinAt($expired['code'], $firstId);
        $this->actingAs($second);
        $secondId = $this->join($limited['code']);
        $this->deleteJson('/api/properties/join-requests/'.$firstId)->assertNotFound();
        $third = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($third);
        $thirdId = $this->join($limited['code']);
        $this->travel(2)->days();
        $this->actingAs($this->owner);
        $this->decide($expiredId)->assertConflict()->assertJsonPath('message', 'Mã mời đã hết hạn. Hãy xin mã mới và gửi lại yêu cầu.');
        $this->decide($expiredId, 'rejected')->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->decide($secondId)->assertOk();
        $this->decide($thirdId)->assertConflict();
        $this->assertDatabaseMissing('property_memberships', ['property_id' => $this->property, 'user_id' => $third->id]);
        $this->actingAs($first);
        $this->getJson('/api/properties/join-requests')->assertOk()->assertJsonPath('data.0.status', 'rejected');
        $this->postJson('/api/properties/join-requests', ['code' => $limited['code']])->assertUnprocessable();
        $this->getJson('/api/tenant/workspace', $this->scope())->assertForbidden();
        $this->assertDatabaseHas('property_invitations', ['id' => $limited['id'], 'approvals' => 1]);
        $this->assertDatabaseMissing('property_memberships', ['property_id' => $this->property, 'user_id' => $first->id]);
    }

    public function test_revoke_is_local_and_approved_nonresident_receives_general_notice(): void
    {
        $invite = $this->invite();
        $user = User::factory()->create(['role' => 'tenant']);
        $waiting = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($user);
        $id = $this->join($invite['code']);
        $this->actingAs($waiting);
        $this->join($invite['code']);
        $this->actingAs($this->owner);
        $this->decide($id)->assertOk();

        $notice = ['title' => 'Lịch bảo trì cổng', 'body' => 'Cổng đóng từ 20 giờ.', 'audience' => 'members'];
        $preview = $this->postJson('/api/owner/announcements/preview', $notice, $this->scope())->assertOk()
            ->assertJsonPath('data.recipient_count', 1)->assertJsonPath('data.recipients.0.user_id', $user->id)->json('data.preview_token');
        $this->postJson('/api/owner/announcements', $notice + [
            'preview_token' => $preview, 'idempotency_key' => 'members-notice-1',
        ], $this->scope())->assertCreated();
        $this->actingAs($user)->getJson('/api/notifications', $this->scope())->assertOk()->assertJsonCount(1, 'data');

        DB::table('property_memberships')->insert([
            'property_id' => $this->otherProperty, 'user_id' => $user->id, 'status' => 'approved', 'role' => 'member',
        ]);
        $this->actingAs($this->owner);
        $this->deleteJson('/api/owner/members/'.$this->owner->id, [], $this->scope())->assertConflict();
        $this->deleteJson('/api/owner/members/'.$user->id, [], $this->scope())->assertNoContent();
        $this->assertDatabaseHas('announcements', ['title' => $notice['title']]);
        $this->actingAs($user)->getJson('/api/notifications', $this->scope())->assertForbidden();
        $this->getJson('/api/tenant/workspace', $this->scope($this->otherProperty))->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
    }

    public function test_rejoining_does_not_restore_a_revoked_invoice_grant(): void
    {
        $invite = $this->invite();
        $member = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($member);
        $id = $this->join($invite['code']);

        $this->actingAs($this->owner);
        $this->decide($id)->assertOk();
        $tenant = $this->postJson('/api/owner/tenants', ['full_name' => 'Người đại diện', 'phone_number' => '0900000000'], $this->scope())
            ->assertCreated()->json('data.id');
        $this->putJson('/api/properties/tenant-link', ['user_id' => $member->id, 'tenant_id' => $tenant], $this->scope())->assertNoContent();
        $room = $this->postJson('/api/owner/rooms', ['room_number' => 'A101', 'capacity' => 2, 'default_monthly_rent' => '2000'], $this->scope())
            ->assertCreated()->json('data.id');
        $group = $this->postJson('/api/owner/rental-groups', ['group_code' => 'TEAM-A'], $this->scope())
            ->assertCreated()->json('data.id');
        $stay = $this->postJson('/api/owner/occupancies', [
            'room_id' => $room, 'rental_group_id' => $group, 'tenant_ids' => [$tenant],
            'representative_tenant_id' => $tenant, 'started_at' => '2026-10-01 00:00:00',
        ], $this->scope())->assertCreated()->json('data.id');
        $invoicePath = '/api/owner/occupancies/'.$stay.'/invoices';
        $payload = ['billing_month' => '2026-10'];
        $token = $this->postJson($invoicePath.'/preview', $payload, $this->scope())->assertOk()->json('data.preview_token');
        $invoice = $this->postJson($invoicePath, $payload + ['preview_token' => $token], $this->scope())->assertCreated()->json('data.id');

        $this->actingAs($member)->getJson('/api/tenant/workspace', $this->scope())->assertOk()->assertJsonPath('data.rental_status', 'current');
        $this->getJson('/api/tenant/invoices/'.$invoice, $this->scope())->assertOk();
        $this->actingAs($this->owner)->deleteJson('/api/owner/members/'.$member->id, [], $this->scope())->assertNoContent();
        $this->assertDatabaseHas('invoices', ['id' => $invoice]);
        $this->assertNotNull(DB::table('invoice_access_grants')->where('invoice_id', $invoice)->value('revoked_at'));

        $this->actingAs($member)->getJson('/api/tenant/invoices/'.$invoice, $this->scope())->assertForbidden();
        $newId = $this->join($invite['code']);
        $this->actingAs($this->owner);
        $this->decide($newId)->assertOk();
        $this->actingAs($member)->getJson('/api/tenant/invoices/'.$invoice, $this->scope())->assertNotFound();
        $this->assertDatabaseCount('invoice_access_grants', 1);
    }

    public function test_invitation_attempts_are_throttled_without_creating_requests(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'tenant']));
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->postJson('/api/properties/join-preview', ['code' => 'not-a-code'])->assertUnprocessable();
        }
        $this->postJson('/api/properties/join-requests', ['code' => 'not-a-code'])->assertTooManyRequests();
        $this->assertDatabaseCount('property_join_requests', 0);
    }

    private function scope(?int $property = null): array
    {
        return ['X-Property-ID' => (string) ($property ?? $this->property)];
    }

    private function invite(array $options = []): array
    {
        return $this->actingAs($this->owner)->postJson('/api/owner/invitations', $options, $this->scope())
            ->assertCreated()->json('data');
    }

    private function join(string $code): int
    {
        return $this->postJson('/api/properties/join-requests', ['code' => $code])->assertCreated()->json('data.id');
    }

    private function joinAt(string $code, int $cancel): int
    {
        $this->deleteJson('/api/properties/join-requests/'.$cancel)->assertOk()->assertJsonPath('data.status', 'withdrawn');

        return $this->join($code);
    }

    private function decide(int $id, string $decision = 'approved')
    {
        return $this->putJson('/api/owner/join-requests/'.$id, ['decision' => $decision], $this->scope());
    }
}
