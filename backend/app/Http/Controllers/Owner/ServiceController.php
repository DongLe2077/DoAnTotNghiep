<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SaveServiceRequest;
use App\Http\Requests\Owner\ServiceIndexRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceCatalog $catalog) {}

    public function index(ServiceIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Service::query()->withCount('occupancyServices');
        if (isset($filters['q']) && $filters['q'] !== '') {
            $query->where(function ($query) use ($filters) {
                $query->where('service_code', 'like', '%'.$filters['q'].'%')->orWhere('name', 'like', '%'.$filters['q'].'%');
            });
        }
        if (isset($filters['billing_method'])) {
            $query->where('billing_method', $filters['billing_method']);
        }
        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return ServiceResource::collection($query->orderBy('service_code')->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function store(SaveServiceRequest $request): JsonResponse
    {
        $service = $this->catalog->create($request->validated())->loadCount('occupancyServices');

        return response()->json(['message' => 'Đã tạo dịch vụ.', 'data' => new ServiceResource($service)], 201);
    }

    public function show(Service $service): ServiceResource
    {
        return new ServiceResource($service->loadCount('occupancyServices'));
    }

    public function update(SaveServiceRequest $request, Service $service): JsonResponse
    {
        $service = $this->catalog->update($service, $request->validated())->loadCount('occupancyServices');

        return response()->json(['message' => 'Đã cập nhật dịch vụ.', 'data' => new ServiceResource($service)]);
    }

    public function destroy(Service $service): Response
    {
        $this->catalog->delete($service);

        return response()->noContent();
    }
}
