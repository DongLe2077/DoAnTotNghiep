<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LiveUpdates;
use App\Services\MonthlyOccupancy;
use App\Services\RoomCatalog;
use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyWorkspaces;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class PhaseSixTest extends TestCase
{
    use CreatesCashBookSchema;

    public function test_history_uses_the_room_status_at_period_end_and_keeps_snapshots(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
        $this->createCashBookSchema();
        foreach (glob(database_path('migrations/2026_11_*.php')) as $file) {
            if (basename($file) !== '2026_11_23_000001_add_one_time_and_rounded_invoices.php') {
                (require $file)->up();
            }
        }
        config(['tenancy.enabled' => true]);
        $owner = User::factory()->create();
        $id = app(PropertyWorkspaces::class)->create($owner, ['name' => 'Khu thử', 'address' => 'Địa chỉ thử'])->id;
        app(PropertyContext::class)->run($id, function () use ($id) {
            $room = app(RoomCatalog::class)->create([
                'room_number' => 'A1', 'capacity' => 2, 'default_monthly_rent' => '2000',
                'operational_status' => 'available',
            ]);
            $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
            $this->assertSame($id, DB::table('rooms')->where('id', $room->id)->value('property_id'));
            $room = app(RoomCatalog::class)->update($room, ['operational_status' => 'maintenance']);
            $report = app(MonthlyOccupancy::class);
            $this->assertSame(1, $report->month('2026-10')['vacant_at_end']);
            $this->assertNull($report->month('2026-09')['vacant_at_end']);
            $this->assertSame(0, $report->rooms()[0]['available_places']);
            $report->archive('2026-10', ['occupancy' => $report->month('2026-10')]);
            $report->archive('2026-10', ['occupancy' => $report->month('2026-10')]);
            $this->assertSame(1, DB::table('monthly_report_snapshots')->where('property_id', $id)->count());
            $revision = app(LiveUpdates::class)->revision('market');
            DB::transaction(function () use ($room) {
                $room->update(['operational_status' => 'available']);
            });
            $live = app(LiveUpdates::class);
            $this->assertNotSame($revision, $live->revision('market'));
            $committed = $live->revision('market');
            DB::beginTransaction();
            DB::table('rooms')->where('id', $room->id)->update(['capacity' => 3]);
            DB::rollBack();
            $this->assertSame($committed, $live->revision('market'));

            config(['tenancy.pusher' => ['id' => '123', 'key' => 'public-test', 'secret' => 'test-only', 'cluster' => 'ap1']]);
            Http::fake(['api-ap1.pusher.com/*' => Http::response()]);
            $live->flush();
            Http::assertSent(fn ($request) => $request['name'] === 'changed'
                && $request['data'] === '{}'
                && in_array('rentbase-market', $request['channels'], true)
                && str_contains($request->url(), 'auth_signature='));
        });
        $this->actingAs($owner)->getJson('/api/live/property?socket=123.456', ['X-Property-ID' => (string) $id])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('client.key', 'public-test')->assertJsonMissingPath('client.secret');
        $this->actingAs(User::factory()->create())->getJson('/api/live/property?socket=123.456', ['X-Property-ID' => (string) $id])
            ->assertForbidden();
        $this->getJson('/api/live/market')->assertOk()->assertJsonPath('channel', 'rentbase-market');
        $this->getJson('/api/live/user')->assertOk()->assertJsonMissingPath('client.secret');
        Http::assertSentCount(1);
    }
}
