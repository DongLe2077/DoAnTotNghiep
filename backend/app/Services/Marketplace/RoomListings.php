<?php

namespace App\Services\Marketplace;

use App\Models\Room;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

class RoomListings
{
    private const SPOTS = 'r.capacity - COALESCE(residents.total, 0)';

    private const RENT = 'COALESCE(o.monthly_rent, r.default_monthly_rent)';

    public function search(array $filters): array
    {
        $query = $this->visible();
        if (isset($filters['area'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['area']).'%';
            $query->whereRaw("l.area LIKE ? ESCAPE '!'", [$pattern]);
        }
        if (isset($filters['min_price'])) {
            $query->whereRaw(self::RENT.' >= CAST(? AS DECIMAL(18,2))', [$filters['min_price']]);
        }
        if (isset($filters['max_price'])) {
            $query->whereRaw(self::RENT.' <= CAST(? AS DECIMAL(18,2))', [$filters['max_price']]);
        }
        if (isset($filters['people'])) {
            $query->whereRaw(self::SPOTS.' >= ?', [(int) $filters['people']]);
        }

        return self::page($query->latest('l.id')->paginate(12), $this->data(...));
    }

    public function find(int $id): array
    {
        $row = $this->visible()->where('l.id', $id)->first();
        abort_unless($row, 404, 'Tin này hiện không nhận khách.');

        return $this->data($row);
    }

    public function photo(int $id): stdClass
    {
        $row = $this->visible()->where('l.id', $id)->whereNotNull('l.photo_mime')
            ->select(['l.photo_mime', 'l.photo_base64'])->first();
        abort_unless($row, 404);

        return $row;
    }

    public function forOwner(int $propertyId): array
    {
        return self::page($this->query()->where('l.property_id', $propertyId)->latest('l.id')->paginate(12),
            fn ($row) => $this->data($row) + [
                'room_id' => $row->room_id === null ? null : (int) $row->room_id,
                'published' => (bool) $row->published, 'accepting' => $this->accepting($row),
            ]);
    }

    public function save(int $propertyId, array $data, ?int $listingId): int
    {
        return DB::transaction(function () use ($propertyId, $data, $listingId) {
            $existing = $listingId === null ? null : DB::table('room_listings')
                ->where('property_id', $propertyId)->where('id', $listingId)->lockForUpdate()->first();
            abort_if($listingId !== null && ! $existing, 404);
            $room = Room::query()->lockForUpdate()->findOrFail($existing ? $existing->room_id : $data['room_id']);
            abort_if($listingId === null && DB::table('room_listings')->where('room_id', $room->id)->exists(), 409,
                'Phòng này đã có tin. Hãy sửa hoặc đăng lại tin hiện có.');
            unset($data['room_id']);
            if ($existing) {
                DB::table('room_listings')->where('id', $existing->id)->update($data + ['updated_at' => now()]);

                return (int) $existing->id;
            }

            return DB::table('room_listings')->insertGetId($data + [
                'property_id' => $propertyId, 'room_id' => $room->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function hide(int $propertyId, int $listingId): void
    {
        $query = DB::table('room_listings')->where('property_id', $propertyId)->where('id', $listingId);
        abort_unless($query->exists(), 404);
        $query->update(['published' => false, 'updated_at' => now()]);
    }

    /** Begins all request and move-in mutations at the room lock used by the tenancy lifecycle. */
    public function availableUnderLock(stdClass $listing, int $people): array
    {
        abort_unless($listing->room_id, 409, 'Phòng không còn nhận khách.');
        $room = Room::query()->lockForUpdate()->find($listing->room_id);
        abort_unless($room, 409, 'Phòng không còn nhận khách.');
        $row = $this->visible()->where('l.id', $listing->id)->first();
        abort_unless($row && (int) $row->spots >= $people, 409, 'Phòng đã hết chỗ, đang bảo trì hoặc đã ngừng nhận yêu cầu. Hãy làm mới danh sách.');

        return [$room, $row->occupancy_id];
    }

    public static function page($rows, callable $map): array
    {
        return ['data' => $rows->getCollection()->map($map)->all(), 'meta' => [
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total(),
        ]];
    }

    private function visible(): Builder
    {
        return $this->query()->where('l.published', true)->where('r.operational_status', 'available')->whereNull('d.id')
            ->whereRaw(self::SPOTS.' > 0')->where(fn ($query) => $query->whereNull('o.id')->orWhere('l.sharing', true));
    }

    private function query(): Builder
    {
        // Other occupants' records never enter the public SELECT list.
        $residents = DB::table('tenant_stays')->whereNull('moved_out_at')
            ->selectRaw('occupancy_id, COUNT(*) AS total')->groupBy('occupancy_id');

        return DB::table('room_listings as l')
            ->join('rental_properties as p', 'p.id', '=', 'l.property_id')
            ->leftJoin('rooms as r', fn ($join) => $join->on('r.id', '=', 'l.room_id')->on('r.property_id', '=', 'l.property_id'))
            ->leftJoin('occupancies as o', fn ($join) => $join->on('o.room_id', '=', 'r.id')->whereNull('o.ended_at'))
            ->leftJoinSub($residents, 'residents', 'residents.occupancy_id', '=', 'o.id')
            ->leftJoin('scheduled_deletions as d', fn ($join) => $join->on('d.record_id', '=', 'r.id')
                ->on('d.property_id', '=', 'l.property_id')->where('d.record_type', 'rooms'))
            ->select(['l.id', 'l.title', 'l.area', 'l.area_m2', 'l.description', 'l.amenities', 'l.fee_notes', 'l.photo_mime',
                'l.sharing', 'l.published', 'l.room_id', 'p.name as property_name', 'r.room_number',
                'r.capacity', 'r.operational_status', 'o.id as occupancy_id', 'd.id as deletion_id'])
            ->selectRaw(self::SPOTS.' AS spots, '.self::RENT.' AS monthly_rent');
    }

    private function accepting(stdClass $row): bool
    {
        return $row->published && $row->operational_status === 'available' && $row->deletion_id === null
            && (int) $row->spots > 0 && ($row->occupancy_id === null || $row->sharing);
    }

    private function data(stdClass $row): array
    {
        return [
            'id' => (int) $row->id, 'title' => $row->title, 'area' => $row->area,
            'area_m2' => $row->area_m2 === null ? null : (float) $row->area_m2,
            'description' => $row->description, 'amenities' => $row->amenities, 'fee_notes' => $row->fee_notes,
            'property_name' => $row->property_name, 'room_number' => $row->room_number, 'capacity' => (int) $row->capacity,
            'monthly_rent' => $row->monthly_rent === null ? null : (string) $row->monthly_rent,
            'spots' => max(0, (int) $row->spots), 'sharing' => (bool) $row->sharing,
            'occupied' => $row->occupancy_id !== null, 'has_photo' => $row->photo_mime !== null,
        ];
    }
}
