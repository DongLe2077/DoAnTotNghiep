<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\LinkMaintenanceExpenseRequest;
use App\Http\Requests\Owner\MaintenanceExpenseIndexRequest;
use App\Http\Requests\Owner\UnlinkMaintenanceExpenseRequest;
use App\Http\Resources\MaintenanceExpenseHistoryResource;
use App\Http\Resources\MaintenanceExpenseResource;
use App\Models\MaintenanceExpenseHistory;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceExpenses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MaintenanceExpenseController extends Controller
{
    public function __construct(private readonly MaintenanceExpenses $expenses) {}

    public function index(MaintenanceExpenseIndexRequest $request, MaintenanceRequest $maintenanceRequest): AnonymousResourceCollection
    {
        return DB::transaction(fn () => MaintenanceExpenseResource::collection($this->expenses->query($maintenanceRequest)
            ->paginate($request->validated('per_page', 15))->withQueryString())
            ->additional(['summary' => $this->expenses->summary($maintenanceRequest)]));
    }

    public function store(LinkMaintenanceExpenseRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $result = $this->expenses->link($maintenanceRequest, $request->validated(), $request->user('web')->id);

        return response()->json(['message' => $result['replayed'] ? 'Yêu cầu liên kết đã được ghi nhận trước đó.' : 'Đã liên kết khoản chi với sửa chữa.',
            'replayed' => $result['replayed'], 'history_id' => $result['history_id'], 'data' => new MaintenanceExpenseResource($result['link'])], $result['replayed'] ? 200 : 201);
    }

    public function unlink(UnlinkMaintenanceExpenseRequest $request, MaintenanceRequest $maintenanceRequest, string $cashTransaction): JsonResponse
    {
        $result = $this->expenses->unlink($maintenanceRequest, (int) $cashTransaction, $request->validated(), $request->user('web')->id);

        return response()->json(['message' => $result['replayed'] ? 'Yêu cầu gỡ liên kết đã được ghi nhận trước đó.' : 'Đã gỡ liên kết. Khoản chi trong sổ thu chi được giữ nguyên.',
            'replayed' => $result['replayed'], 'history_id' => $result['history_id'], 'data' => new MaintenanceExpenseResource($result['link'])]);
    }

    public function history(MaintenanceExpenseIndexRequest $request, MaintenanceRequest $maintenanceRequest): AnonymousResourceCollection
    {
        return MaintenanceExpenseHistoryResource::collection(MaintenanceExpenseHistory::query()
            ->where('maintenance_request_id', $maintenanceRequest->id)->orderByDesc('id')
            ->paginate($request->validated('per_page', 10))->withQueryString());
    }

    public function context(MaintenanceExpenseIndexRequest $request, string $cashTransaction): JsonResponse
    {
        $link = $this->expenses->context((int) $cashTransaction);

        return response()->json(['data' => $link === null ? null : new MaintenanceExpenseResource($link)]);
    }
}
