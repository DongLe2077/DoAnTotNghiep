<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RoomIndexRequest;
use App\Http\Requests\Owner\SaveRoomRequest;
use App\Http\Requests\Owner\UpdateRentalRateRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\RoomCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RoomController extends Controller
{
    public function __construct(private readonly RoomCatalog $catalog) {}

    public function index(RoomIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $this->catalog->query();
        if (isset($filters['q']) && $filters['q'] !== '') {
            $query->where('rooms.room_number', 'like', '%'.$filters['q'].'%');
        }
        if (isset($filters['operational_status'])) {
            $query->where('rooms.operational_status', $filters['operational_status']);
        }
        if (isset($filters['occupancy_status'])) {
            $query->where('summary.occupancy_status', $filters['occupancy_status']);
        }

        return RoomResource::collection($query->orderBy('rooms.room_number')->orderBy('rooms.id')
            ->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function store(SaveRoomRequest $request): JsonResponse
    {
        $room = $this->catalog->create($request->validated());

        return response()->json(['message' => 'Đã tạo phòng.', 'data' => new RoomResource($this->catalog->query()->where('rooms.id', $room->id)->firstOrFail())], 201);
    }

    public function show(Room $room): RoomResource
    {
        return new RoomResource($this->catalog->query()->where('rooms.id', $room->id)->firstOrFail());
    }

    public function update(SaveRoomRequest $request, Room $room): JsonResponse
    {
        $room = $this->catalog->update($room, $request->validated());

        return response()->json(['message' => 'Đã cập nhật phòng.', 'data' => new RoomResource($this->catalog->query()->where('rooms.id', $room->id)->firstOrFail())]);
    }

    public function destroy(Room $room): Response
    {
        $this->catalog->delete($room);

        return response()->noContent();
    }

    public function rentalRate(): JsonResponse
    {
        return response()->json(['data' => $this->catalog->rentalRate()]);
    }

    public function updateRentalRate(UpdateRentalRateRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Đã cập nhật giá thuê mặc định cho tất cả phòng. Giá đã chốt cho các đợt thuê được giữ nguyên.',
            'data' => $this->catalog->updateRentalRate($request->validated('default_monthly_rent')),
        ]);
    }
}
