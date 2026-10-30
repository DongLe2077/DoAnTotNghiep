<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\BulkMeterIndexRequest;
use App\Http\Requests\Owner\RecordBulkMeterReadingRequest;
use App\Http\Resources\MeterReadingResource;
use App\Services\BulkMeterReadings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BulkMeterReadingController extends Controller
{
    public function __construct(private readonly BulkMeterReadings $readings) {}

    public function index(BulkMeterIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return DB::transaction(function () use ($filters) {
            $page = $this->readings->query($filters)->paginate($filters['per_page'] ?? 15)->withQueryString();
            $page->through(fn ($service) => $this->readings->describe($service, $filters['billing_month']));

            return JsonResource::collection($page);
        });
    }

    public function store(RecordBulkMeterReadingRequest $request): JsonResponse
    {
        $result = $this->readings->record($request->validated(), $request->user('web'));

        return response()->json(['message' => $result['replayed'] ? 'Yêu cầu chỉ số đã được ghi nhận trước đó; trả trạng thái hiện tại.' : 'Đã ghi chỉ số cho dòng đã chọn.',
            'data' => $result['reading'] === null ? null : new MeterReadingResource($result['reading']),
            ...Arr::except($result, ['reading'])], $result['replayed'] ? 200 : 201);
    }
}
