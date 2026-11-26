<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomCatalog
{
    public function query(): Builder
    {
        return Room::query()
            ->leftJoin('room_occupancy_summary as summary', 'summary.room_id', '=', 'rooms.id')
            ->select('rooms.*', 'summary.resident_count', 'summary.occupancy_status')
            ->withCount('occupancies');
    }

    public function rentalRate(): array
    {
        return $this->rateSummary(Room::query()->orderBy('id')->get(['id', 'default_monthly_rent']));
    }

    public function updateRentalRate(string|int|float $rate): array
    {
        return DB::transaction(function () use ($rate) {
            $rooms = $this->lockRooms();
            abort_if($rooms->isEmpty(), 409, 'Hãy tạo phòng đầu tiên kèm giá thuê mặc định trước.');
            Room::query()->update(['default_monthly_rent' => $this->normalizeRate($rate)]);

            return $this->rentalRate();
        }, 3);
    }

    public function create(array $attributes): Room
    {
        try {
            return DB::transaction(function () use ($attributes) {
                $rooms = $this->lockRooms();
                $rate = $this->rateSummary($rooms)['default_monthly_rent'];
                if ($rate === null && ! array_key_exists('default_monthly_rent', $attributes)) {
                    throw ValidationException::withMessages(['default_monthly_rent' => 'Nhập giá thuê mặc định cho phòng này.']);
                }
                $attributes['default_monthly_rent'] = isset($attributes['default_monthly_rent'])
                    ? $this->normalizeRate($attributes['default_monthly_rent']) : $rate;

                $room = Room::create($attributes);
                app(MonthlyOccupancy::class)->track($room, $room->operational_status ?? 'available');
                return $room;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['room_number' => 'Số phòng đã tồn tại.']);
        }
    }

    public function update(Room $room, array $attributes): Room
    {
        try {
            return DB::transaction(function () use ($room, $attributes) {
                $rooms = $this->lockRooms();
                $lockedRoom = $rooms->firstWhere('id', $room->id);
                abort_unless($lockedRoom instanceof Room, 404);
                $residents = (int) \App\Services\Tenancy\PropertyDatabase::table('room_occupancy_summary')->where('room_id', $room->id)->value('resident_count');
                if (isset($attributes['capacity']) && (int) $attributes['capacity'] < $residents) {
                    throw ValidationException::withMessages(['capacity' => "Phòng hiện có {$residents} người ở; sức chứa không được thấp hơn số này."]);
                }
                $lockedRoom->update($attributes);
                if ($lockedRoom->wasChanged('operational_status')) {
                    app(MonthlyOccupancy::class)->track($lockedRoom, $lockedRoom->operational_status);
                }

                return $lockedRoom;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['room_number' => 'Số phòng đã tồn tại.']);
        }
    }

    public function delete(Room $room): void
    {
        try {
            DB::transaction(function () use ($room) {
                $lockedRoom = Room::query()->lockForUpdate()->findOrFail($room->id);
                abort_if($lockedRoom->occupancies()->exists(), 409, 'Phòng đã có lịch sử thuê nên không thể xóa. Có thể chuyển sang trạng thái bảo trì.');
                app(MonthlyOccupancy::class)->track($lockedRoom, 'deleted');
                $lockedRoom->delete();
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23503'], true)) {
                abort(409, 'Phòng đang được dữ liệu thuê tham chiếu nên không thể xóa.');
            }
            throw $exception;
        }
    }

    private function lockRooms(): Collection
    {
        // A common lock order keeps creates and shared-rate changes consistent.
        return Room::query()->orderBy('id')->lockForUpdate()->get();
    }

    private function rateSummary(Collection $rooms): array
    {
        $rates = $rooms->pluck('default_monthly_rent')->unique()->values();

        return [
            'default_monthly_rent' => $rates->count() === 1 ? $rates->first() : null,
            'room_count' => $rooms->count(),
            'is_uniform' => $rates->count() <= 1,
        ];
    }

    private function normalizeRate(string|int|float $rate): string
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $rate, 2), 2, '');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 2, '0');
    }
}
