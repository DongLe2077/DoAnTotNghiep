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
                $rate = $this->uniformRate($rooms);
                if ($rate === null && ! array_key_exists('default_monthly_rent', $attributes)) {
                    throw ValidationException::withMessages(['default_monthly_rent' => 'Phòng đầu tiên cần có giá thuê mặc định.']);
                }
                $this->validateRoomRate($attributes, $rate);
                $attributes['default_monthly_rent'] = $rate ?? $this->normalizeRate($attributes['default_monthly_rent']);

                return Room::create($attributes);
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
                $this->validateRoomRate($attributes, $this->uniformRate($rooms));
                $residents = (int) DB::table('room_occupancy_summary')->where('room_id', $room->id)->value('resident_count');
                if (isset($attributes['capacity']) && (int) $attributes['capacity'] < $residents) {
                    throw ValidationException::withMessages(['capacity' => "Phòng hiện có {$residents} người ở; sức chứa không được thấp hơn số này."]);
                }
                $lockedRoom->update($attributes);

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

    private function uniformRate(Collection $rooms): ?string
    {
        $summary = $this->rateSummary($rooms);
        abort_unless($summary['is_uniform'], 409, 'Giá thuê các phòng chưa đồng nhất. Hãy cập nhật giá thuê chung trước.');

        return $summary['default_monthly_rent'];
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

    private function validateRoomRate(array $attributes, ?string $rate): void
    {
        if ($rate !== null && array_key_exists('default_monthly_rent', $attributes) && $this->normalizeRate($attributes['default_monthly_rent']) !== $rate) {
            throw ValidationException::withMessages(['default_monthly_rent' => 'Các phòng dùng giá thuê mặc định chung. Hãy dùng chức năng cập nhật giá thuê chung để thay đổi.']);
        }
    }

    private function normalizeRate(string|int|float $rate): string
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $rate, 2), 2, '');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 2, '0');
    }
}
