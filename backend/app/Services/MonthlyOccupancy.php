<?php

namespace App\Services;

use App\Models\Room;
use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonthlyOccupancy
{
    public function enabled(): bool
    {
        return app(PropertyContext::class)->id() !== null && Schema::hasTable('reporting_baseline');
    }

    public function track(Room $room, string $status): void
    {
        if ($this->enabled()) {
            DB::table('room_status_events')->insert([
                'property_id' => app(PropertyContext::class)->requireId(), 'room_id' => $room->id,
                'status' => $status, 'recorded_at' => now(),
            ]);
        }
    }

    public function removeRooms(array $ids): void
    {
        if ($ids && $this->enabled()) {
            PropertyDatabase::table('rooms')->whereIn('id', $ids)->orderBy('id')->chunkById(200, function ($rooms) {
                DB::table('room_status_events')->insert($rooms->map(fn ($room) => [
                    'property_id' => $room->property_id, 'room_id' => $room->id,
                    'status' => 'deleted', 'recorded_at' => now(),
                ])->all());
            });
        }
    }

    public function rooms(): array
    {
        $pending = PropertyDatabase::table('scheduled_deletions')->where('record_type', 'rooms')->pluck('record_id')->map(intval(...))->flip();

        return PropertyDatabase::table('room_occupancy_summary as r')->orderBy('r.room_number')->get()
            ->map(function ($room) use ($pending) {
                $waiting = $pending->has((int) $room->room_id);

                return [
                    'room_id' => (int) $room->room_id, 'room_number' => $room->room_number,
                    'resident_count' => (int) $room->resident_count, 'capacity' => (int) $room->capacity,
                    'status' => $waiting ? 'pending_deletion' : $room->occupancy_status,
                    'available_places' => ! $waiting && $room->operational_status === 'available'
                        ? max(0, (int) $room->capacity - (int) $room->resident_count) : 0,
                ];
            })->all();
    }

    public function month(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'));
        $end = $start->addMonth();
        $future = $start->isFuture();
        $until = $end->min(now()->addSecond())->toDateTimeString();
        $from = $start->toDateTimeString();
        $occupancies = PropertyDatabase::table('occupancies as o');
        $stays = PropertyDatabase::table('tenant_stays as s')
            ->joinSub(clone $occupancies, 'stay', 'stay.id', '=', 's.occupancy_id');
        $entries = (clone $stays)->where('s.moved_in_at', '>=', $from)->where('s.moved_in_at', '<', $until);
        $exits = (clone $stays)->where('s.moved_out_at', '>=', $from)->where('s.moved_out_at', '<', $until);
        $transfers = (clone $entries)->whereExists(function ($query) use ($stays) {
            $query->selectRaw('1')->fromSub((clone $stays)->select('s.*'), 'prior')
                ->whereColumn('prior.occupancy_id', 'stay.previous_occupancy_id')
                ->whereColumn('prior.tenant_id', 's.tenant_id')->whereColumn('prior.moved_out_at', 's.moved_in_at');
        })->count();
        $residents = (clone $stays)->where('s.moved_in_at', '<', $until)
            ->where(fn ($query) => $query->whereNull('s.moved_out_at')->orWhere('s.moved_out_at', '>=', $until));
        $vacant = null;
        if ($this->enabled() && ! $future && DB::table('reporting_baseline')->value('started_at') < $until) {
            $events = DB::table('room_status_events')->where('property_id', app(PropertyContext::class)->requireId())
                ->where('recorded_at', '<', $until)->selectRaw('MAX(id) as id')->groupBy('room_id');
            $vacant = DB::table('room_status_events as e')->whereIn('e.id', $events)->where('e.status', 'available')
                ->whereNotIn('e.room_id', (clone $residents)->select('stay.room_id'))->count();
        }

        return [
            'period_closed' => ! $end->isFuture(), 'resident_at_end' => $future ? null : $residents->distinct()->count('s.tenant_id'),
            'vacant_at_end' => $vacant, 'arrivals' => $entries->count() - $transfers,
            'departures' => $exits->count() - $transfers, 'transfers' => $transfers,
            'rooms_used' => $future ? 0 : (clone $stays)->where('s.moved_in_at', '<', $until)
                ->where(fn ($query) => $query->whereNull('s.moved_out_at')->orWhere('s.moved_out_at', '>', $from))
                ->distinct()->count('stay.room_id'),
        ];
    }

    public function archive(string $month, array $totals): void
    {
        $json = json_encode($totals, JSON_THROW_ON_ERROR);
        DB::table('monthly_report_snapshots')->insertOrIgnore([
            'property_id' => app(PropertyContext::class)->requireId(), 'month' => $month,
            'digest' => hash('sha256', $json), 'totals' => $json, 'captured_at' => now(),
        ]);
    }

    public function snapshot(string $month): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $latest = DB::table('monthly_report_snapshots')->where('property_id', app(PropertyContext::class)->requireId())
            ->where('month', $month)->latest('id')->first();

        return $latest ? ['captured_at' => $latest->captured_at, 'totals' => json_decode($latest->totals, true, flags: JSON_THROW_ON_ERROR)] : null;
    }
}
