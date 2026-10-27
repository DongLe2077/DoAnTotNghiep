<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceHistoryIndexRequest;
use App\Http\Requests\Owner\MaintenanceIndexRequest;
use App\Http\Requests\Owner\ProcessMaintenanceRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenanceRequestController extends Controller
{
    public function __construct(private readonly MaintenanceManagement $management) {}

    public function index(MaintenanceIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return MaintenanceRequestResource::collection($this->management->query($filters)->orderByDesc('submitted_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function show(MaintenanceRequest $maintenanceRequest): MaintenanceRequestResource
    {
        return new MaintenanceRequestResource($this->management->query()->findOrFail($maintenanceRequest->id));
    }

    public function update(ProcessMaintenanceRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $report = $this->management->process($maintenanceRequest, $request->validated(), $request->user('web'));

        return response()->json(['message' => 'Đã cập nhật xử lý yêu cầu sửa chữa.',
            'data' => new MaintenanceRequestResource($this->management->query()->findOrFail($report->id))]);
    }

    public function history(MaintenanceHistoryIndexRequest $request, MaintenanceRequest $maintenanceRequest)
    {
        return $this->management->history($maintenanceRequest, $request->validated('per_page', 10));
    }
}
