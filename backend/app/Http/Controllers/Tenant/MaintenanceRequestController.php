<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceHistoryIndexRequest;
use App\Http\Requests\Tenant\MaintenanceIndexRequest;
use App\Http\Requests\Tenant\StoreMaintenanceRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenanceRequestController extends Controller
{
    public function __construct(private readonly MaintenanceManagement $management) {}

    public function index(MaintenanceIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return MaintenanceRequestResource::collection($this->management->query($filters, $request->user('web')->id)
            ->orderByDesc('submitted_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function eligibleOccupancies(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->management->eligibleOccupancies($request->user('web')->id)->get()->map(fn ($occupancy) => [
            'occupancy_id' => $occupancy->id, 'room' => $occupancy->room->only(['id', 'room_number']),
            'group' => $occupancy->rentalGroup->only(['id', 'group_code']),
        ])]);
    }

    public function show(Request $request, string $maintenanceRequest): MaintenanceRequestResource
    {
        return new MaintenanceRequestResource($this->management->query(authorId: $request->user('web')->id)->findOrFail($maintenanceRequest));
    }

    public function history(MaintenanceHistoryIndexRequest $request, string $maintenanceRequest)
    {
        $report = MaintenanceRequest::query()->where('submitted_by', $request->user('web')->id)->findOrFail($maintenanceRequest);

        return $this->management->history($report, $request->validated('per_page', 10));
    }

    public function store(StoreMaintenanceRequest $request, string $occupancy): JsonResponse
    {
        $result = $this->management->submit($occupancy, $request->validated(), $request->user('web'));

        return response()->json([
            'message' => $result['replayed'] ? 'Yêu cầu giống hệt đang được xử lý; trả lại yêu cầu đã gửi.' : 'Đã gửi yêu cầu báo hỏng.',
            'replayed' => $result['replayed'],
            'data' => new MaintenanceRequestResource($this->management->query(authorId: $request->user('web')->id)->findOrFail($result['request']->id)),
        ], $result['replayed'] ? 200 : 201);
    }
}
