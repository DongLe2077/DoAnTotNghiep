<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use App\Services\Announcements;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use CreatesBillingSchema;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 24)->setTime(9, 0));
        $this->createBillingSchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
    }

    public function test_all_targets_all_active_residents_including_non_representatives_but_excludes_former_future_disabled_and_unlinked_users(): void
    {
        [$first, $room] = $this->resident();
        [$second] = $this->resident($room);
        [, $maintenanceRoom] = $this->resident();
        $maintenanceRoom->update(['operational_status' => 'maintenance']);
        [$disabled] = $this->resident();
        $disabled->update(['is_active' => false]);
        [$former] = $this->resident();
        TenantStay::where('tenant_id', $former->tenant_id)->update(['moved_out_at' => '2026-10-02 00:00:00']);
        [$future] = $this->resident();
        TenantStay::where('tenant_id', $future->tenant_id)->update(['moved_in_at' => '2026-11-01 00:00:00']);
        User::factory()->create(['role' => 'tenant']);
        $preview = $this->preview()->assertOk()->assertJsonPath('data.recipient_count', 3)->assertJsonPath('data.can_send', true);
        $ids = array_column($preview->json('data.recipients'), 'user_id');
        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);
        $this->assertNotContains($disabled->id, $ids);
        $this->publish($this->payload($preview))->assertCreated()->assertJsonPath('data.recipient_count', 3)->assertJsonPath('data.read_count', 0);
        $this->assertSame(3, AppNotification::count());
    }

    public function test_selected_rooms_receive_once_and_empty_audience_cannot_send(): void
    {
        [$first, $room] = $this->resident();
        [$second] = $this->resident();
        $values = ['audience' => 'rooms', 'room_ids' => [$room->id]];
        $preview = $this->preview($values)->assertJsonPath('data.recipient_count', 1);
        $id = $this->publish($this->payload($preview, $values))->assertCreated()->json('data.id');
        $this->assertSame([$first->id], AppNotification::pluck('user_id')->all());
        $this->actingAs($second, 'web');
        $this->getJson('/api/tenant/announcements/'.$id)->assertNotFound();
        $this->getJson('/api/notifications?type=owner_announcement')->assertJsonCount(0, 'data');
        $this->actingAs($this->owner, 'web');
        $room = Room::create(['room_number' => 'EMPTY', 'capacity' => 2, 'default_monthly_rent' => '100', 'operational_status' => 'available']);
        $values = ['audience' => 'rooms', 'room_ids' => [$room->id]];
        $empty = $this->preview($values)->assertJsonPath('data.can_send', false)->assertJsonPath('data.recipient_count', 0);
        $this->publish($this->payload($empty, $values))->assertConflict();
        $this->assertSame(1, Announcement::count());
    }

    public function test_stale_recipient_list_or_changed_content_requires_another_preview(): void
    {
        [$user, $room] = $this->resident();
        $preview = $this->preview();
        $user->update(['is_active' => false]);
        $this->publish($this->payload($preview))->assertConflict();
        $user->update(['is_active' => true]);
        $preview = $this->preview();
        $this->resident($room);
        $this->publish($this->payload($preview))->assertConflict();
        $preview = $this->preview();
        $this->publish($this->payload($preview, ['body' => 'Nội dung đã thay đổi']))->assertConflict();
        $this->assertSame(0, Announcement::count());
        $this->assertSame(0, AppNotification::count());
    }

    public function test_exact_retry_after_a_resident_leaves_does_not_resend_and_key_reuse_with_other_content_or_actor_is_rejected(): void
    {
        [$user] = $this->resident();
        $payload = $this->payload($this->preview());
        $id = $this->publish($payload)->assertCreated()->json('data.id');
        TenantStay::where('tenant_id', $user->tenant_id)->update(['moved_out_at' => now()]);
        $this->publish($payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $id);
        $this->publish(array_replace($payload, ['title' => 'Khác']))->assertConflict();
        $this->actingAs(User::factory()->create(), 'web');
        $this->publish($payload)->assertConflict();
        $this->assertSame(1, Announcement::count());
        $this->assertSame(1, AppNotification::count());
        $this->actingAs($user, 'web');
        $this->getJson('/api/tenant/announcements/'.$id)->assertOk()->assertJsonMissingPath('data.recipient_count')
            ->assertJsonMissingPath('data.rooms')->assertJsonMissingPath('data.created_by');
    }

    public function test_owner_can_track_read_receipts_and_reading_one_or_all_is_scoped_to_the_recipient(): void
    {
        [$first, $room] = $this->resident();
        [$second] = $this->resident($room);
        $id = $this->publish($this->payload($this->preview()))->assertCreated()->json('data.id');
        $receipts = $this->getJson('/api/owner/announcements/'.$id.'/recipients')->assertOk()->assertJsonCount(2, 'data')->json('data');
        $firstId = collect($receipts)->firstWhere('user_id', $first->id)['notification_id'];
        $secondId = collect($receipts)->firstWhere('user_id', $second->id)['notification_id'];
        $this->actingAs($first, 'web');
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 1);
        $this->getJson('/api/tenant/announcements/'.$id)->assertJsonPath('data.is_read', false);
        $this->patchJson('/api/notifications/'.$secondId.'/read')->assertNotFound();
        $this->patchJson('/api/notifications/'.$firstId.'/read')->assertOk();
        $this->patchJson('/api/notifications/'.$firstId.'/read')->assertOk();
        $this->actingAs($this->owner, 'web');
        $this->getJson('/api/owner/announcements/'.$id)->assertJsonPath('data.read_count', 1)->assertJsonPath('data.unread_count', 1);
        $this->getJson('/api/owner/announcements/'.$id.'/recipients?read_status=read&per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $first->id);
        $this->getJson('/api/owner/announcements/'.$id.'/recipients?read_status=unread')->assertJsonPath('data.0.user_id', $second->id);
        $this->actingAs($second, 'web');
        $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('data.updated_count', 1);
        $this->actingAs($this->owner, 'web');
        $this->getJson('/api/owner/announcements/'.$id)->assertJsonPath('data.read_count', 2);
        $this->getJson('/api/notifications?type=owner_announcement')->assertJsonCount(0, 'data');
    }

    public function test_new_resident_does_not_inherit_announcements_already_delivered_to_a_room(): void
    {
        [$first, $room] = $this->resident();
        $id = $this->publish($this->payload($this->preview()))->assertCreated()->json('data.id');
        TenantStay::where('tenant_id', $first->tenant_id)->update(['moved_out_at' => now()]);
        [$new] = $this->resident($room);
        $this->actingAs($new, 'web');
        $this->getJson('/api/tenant/announcements/'.$id)->assertNotFound();
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
        $this->actingAs($first, 'web');
        $this->getJson('/api/tenant/announcements/'.$id)->assertOk();
    }

    public function test_validation_role_and_csrf_guards_and_literal_search(): void
    {
        [$user] = $this->resident();
        foreach ([['title' => ' '], ['body' => ' '], ['audience' => 'invalid'], ['audience' => 'rooms'],
            ['audience' => 'rooms', 'room_ids' => [999]], ['recipient_user_ids' => [$user->id]], ['created_by' => $this->owner->id]] as $invalid) {
            $this->preview($invalid)->assertUnprocessable();
        }
        $preview = $this->preview(['title' => 'Lịch 100%_hôm nay']);
        $this->publish($this->payload($preview, ['title' => 'Lịch 100%_hôm nay']))->assertCreated();
        $this->getJson('/api/owner/announcements?q=100%25_')->assertJsonCount(1, 'data');
        $this->getJson('/api/owner/announcements?q=200%25_')->assertJsonCount(0, 'data');
        $this->actingAs($user, 'web');
        $this->postJson('/api/owner/announcements/preview', [])->assertForbidden();
        $this->postJson('/api/owner/announcements', [])->assertForbidden();
        $this->getJson('/api/owner/announcements/1/recipients')->assertForbidden();
        $this->actingAs($this->owner, 'web');
        $payload = $this->payload($this->preview());
        $this->app->instance('env', 'local');
        $this->withSession(['_token' => str_repeat('a', 40)]);
        $this->publish($payload)->assertStatus(419);
        $this->assertSame(1, Announcement::count());
    }

    public function test_delivery_failure_rolls_back_the_announcement_and_all_notifications(): void
    {
        $this->resident();
        $payload = $this->payload($this->preview());
        DB::unprepared("CREATE TRIGGER reject_announcement BEFORE INSERT ON app_notifications WHEN NEW.type = 'owner_announcement' BEGIN SELECT RAISE(ABORT, 'test delivery failure'); END");
        try {
            app(Announcements::class)->publish($payload, $this->owner->id);
            $this->fail('Expected transaction rollback.');
        } catch (QueryException) {
            $this->assertSame(0, Announcement::count());
            $this->assertSame(0, AppNotification::count());
        }
    }

    public function test_sqlite_upgrade_preserves_legacy_notifications_with_inline_unique_keys(): void
    {
        $migration = require database_path('migrations/2026_10_24_000001_create_announcements.php');
        $migration->down();
        // Match the pre-existing manual-demo DDL, including SQLite's reserved autoindex name.
        DB::statement('DROP TABLE app_notifications');
        DB::statement('CREATE TABLE app_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL,
            invoice_id INTEGER, maintenance_request_id INTEGER, dedupe_key TEXT NOT NULL UNIQUE, data TEXT NOT NULL, read_at DATETIME, created_at DATETIME NOT NULL)');
        $legacy = ['user_id' => $this->owner->id, 'type' => 'maintenance_submitted', 'dedupe_key' => str_repeat('a', 64),
            'data' => json_encode(['title' => 'Giữ nguyên nội dung', 'body' => 'Thông báo có trước nâng cấp'], JSON_THROW_ON_ERROR), 'created_at' => now()->format('Y-m-d H:i:s')];
        DB::table('app_notifications')->insert($legacy);
        $before = (array) DB::table('app_notifications')->first();
        $migration->up();
        $after = (array) DB::table('app_notifications')->first();
        unset($after['announcement_id']);
        $this->assertSame($before, $after);
        $migration->down();
        $this->assertSame($before, (array) DB::table('app_notifications')->first());
        try {
            DB::table('app_notifications')->insert($legacy);
            $this->fail('Legacy dedupe constraint must remain intact.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('app_notifications')->count());
        }
    }

    private function preview(array $values = [])
    {
        return $this->postJson('/api/owner/announcements/preview', array_replace(['title' => 'Thông báo chung', 'body' => "Kiểm tra nước ngày mai.\nXin cảm ơn.", 'audience' => 'all'], $values));
    }

    private function payload($preview, array $values = []): array
    {
        return array_replace(['title' => 'Thông báo chung', 'body' => "Kiểm tra nước ngày mai.\nXin cảm ơn.", 'audience' => 'all',
            'preview_token' => $preview->json('data.preview_token'), 'idempotency_key' => (string) Str::uuid()], $values);
    }

    private function publish(array $payload)
    {
        return $this->postJson('/api/owner/announcements', $payload);
    }

    private function resident(?Room $room = null): array
    {
        $room ??= Room::create(['room_number' => 'ANN-'.(Room::count() + 1), 'capacity' => 6, 'default_monthly_rent' => '100', 'operational_status' => 'available']);
        $occupancy = Occupancy::where('room_id', $room->id)->whereNull('ended_at')->first();
        if ($occupancy === null) {
            $group = RentalGroup::create(['group_code' => (string) Str::uuid()]);
            $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-10-01 00:00:00', 'monthly_rent' => '100']);
        }
        $tenant = Tenant::create(['full_name' => 'Người nhận '.(Tenant::count() + 1), 'phone_number' => '0900000000']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        TenantStay::create(['tenant_id' => $tenant->id, 'occupancy_id' => $occupancy->id, 'moved_in_at' => '2026-10-01 00:00:00']);

        return [$user, $room];
    }
}
