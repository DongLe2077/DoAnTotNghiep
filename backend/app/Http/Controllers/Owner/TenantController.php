<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RentalGroupIndexRequest;
use App\Http\Requests\Owner\SaveTenantRequest;
use App\Http\Requests\Owner\TenantAccountRequest;
use App\Http\Requests\Owner\TenantIndexRequest;
use App\Http\Resources\AuthUserResource;
use App\Http\Resources\TenantResource;
use App\Http\Resources\TenantStayResource;
use App\Models\Tenant;
use App\Services\TenantRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TenantController extends Controller
{
    public function __construct(private readonly TenantRegistry $registry) {}

    public function index(TenantIndexRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();
        $query = $this->registry->query();
        if (isset($data['q']) && $data['q'] !== '') {
            $query->where(fn ($query) => $query->where('full_name', 'like', '%'.$data['q'].'%')
                ->orWhere('phone_number', 'like', '%'.$data['q'].'%')->orWhere('identity_number', 'like', '%'.$data['q'].'%'));
        }
        if (array_key_exists('is_residing', $data)) {
            $query->has('stays', $request->boolean('is_residing') ? '>=' : '<', 1, 'and', fn ($query) => $query->whereNull('moved_out_at')
                ->whereHas('occupancy', fn ($query) => $query->whereNull('ended_at')));
        }

        return TenantResource::collection($query->orderBy('full_name')->orderBy('id')->paginate($data['per_page'] ?? 15)->withQueryString());
    }

    public function store(SaveTenantRequest $request): JsonResponse
    {
        $tenant = $this->registry->create($request->validated());

        return response()->json(['message' => 'Đã tạo hồ sơ người thuê.', 'data' => $this->resource($tenant)], 201);
    }

    public function show(Tenant $tenant): TenantResource
    {
        return $this->resource($tenant);
    }

    public function update(SaveTenantRequest $request, Tenant $tenant): JsonResponse
    {
        return response()->json(['message' => 'Đã cập nhật hồ sơ.', 'data' => $this->resource($this->registry->update($tenant, $request->validated()))]);
    }

    public function destroy(Tenant $tenant): Response
    {
        $this->registry->delete($tenant);

        return response()->noContent();
    }

    public function account(TenantAccountRequest $request, Tenant $tenant): JsonResponse
    {
        return response()->json(['message' => 'Đã lưu tài khoản người thuê.', 'data' => new AuthUserResource($this->registry->saveAccount($tenant, $request->validated()))]);
    }

    public function stays(RentalGroupIndexRequest $request, Tenant $tenant): AnonymousResourceCollection
    {
        return TenantStayResource::collection($tenant->stays()->with('tenant', 'occupancy.room')->orderByDesc('moved_in_at')->orderByDesc('id')
            ->paginate($request->validated('per_page', 15))->withQueryString());
    }

    private function resource(Tenant $tenant): TenantResource
    {
        return new TenantResource($this->registry->query()->findOrFail($tenant->id));
    }
}
