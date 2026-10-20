<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ListMeterReadingsRequest;
use App\Http\Requests\Owner\MeterReadingRequest;
use App\Http\Resources\MeterReadingResource;
use App\Models\MeterReading;
use App\Models\Occupancy;
use App\Services\MeterReadingManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MeterReadingController extends Controller
{
    public function __construct(private readonly MeterReadingManager $manager) {}

    public function index(ListMeterReadingsRequest $request, Occupancy $occupancy): AnonymousResourceCollection
    {
        $data = $request->validated();
        $query = $this->query()->whereHas('occupancyService', fn ($query) => $query->where('occupancy_id', $occupancy->id));
        if (isset($data['occupancy_service_id'])) {
            $query->where('occupancy_service_id', $data['occupancy_service_id']);
        }
        if (isset($data['billing_month'])) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $data['billing_month'], config('app.timezone'));
            $query->where('period_start', '>=', $month)->where('period_start', '<', $month->addMonth());
        }

        return MeterReadingResource::collection($query->orderByDesc('period_end')->orderByDesc('id')
            ->paginate($data['per_page'] ?? 15)->withQueryString());
    }

    public function store(MeterReadingRequest $request, Occupancy $occupancy): JsonResponse
    {
        return $this->response($this->manager->create($occupancy, $request->validated(), $request->user('web')->id), 'Đã ghi chỉ số điện nước.', 201);
    }

    public function update(MeterReadingRequest $request, Occupancy $occupancy, MeterReading $reading): JsonResponse
    {
        return $this->response($this->manager->update($occupancy, $reading, $request->validated()), 'Đã sửa kỳ chỉ số cuối chưa lập hóa đơn.');
    }

    public function destroy(Occupancy $occupancy, MeterReading $reading): Response
    {
        $this->manager->delete($occupancy, $reading);

        return response()->noContent();
    }

    private function query(): Builder
    {
        return MeterReading::query()->with('occupancyService.service', 'invoiceItem:id,meter_reading_id,invoice_id');
    }

    private function response(MeterReading $reading, string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => new MeterReadingResource($this->query()->findOrFail($reading->id))], $status);
    }
}
