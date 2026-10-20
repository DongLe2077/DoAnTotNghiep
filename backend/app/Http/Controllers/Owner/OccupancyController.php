<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\AddMembersRequest;
use App\Http\Requests\Owner\ApplyServiceRequest;
use App\Http\Requests\Owner\ChangeRepresentativeRequest;
use App\Http\Requests\Owner\CheckInRequest;
use App\Http\Requests\Owner\CheckoutRequest;
use App\Http\Requests\Owner\EndServiceRequest;
use App\Http\Requests\Owner\LeaveMemberRequest;
use App\Http\Requests\Owner\OccupancyIndexRequest;
use App\Http\Requests\Owner\TransferOccupancyRequest;
use App\Http\Resources\OccupancyResource;
use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\TenantStay;
use App\Services\TenancyLifecycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OccupancyController extends Controller
{
    public function __construct(private readonly TenancyLifecycle $lifecycle) {}

    public function index(OccupancyIndexRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();
        $query = $this->query();
        if (isset($data['status'])) {
            $data['status'] === 'active' ? $query->whereNull('ended_at') : $query->whereNotNull('ended_at');
        }
        foreach (['room_id', 'rental_group_id'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['tenant_id'])) {
            $query->whereHas('tenantStays', fn ($query) => $query->where('tenant_id', $data['tenant_id']));
        }

        return OccupancyResource::collection($query->orderByDesc('started_at')->orderByDesc('id')->paginate($data['per_page'] ?? 15)->withQueryString());
    }

    public function store(CheckInRequest $request): JsonResponse
    {
        return $this->response($this->lifecycle->checkIn($request->validated()), 'Đã nhận phòng.', 201);
    }

    public function show(Occupancy $occupancy): OccupancyResource
    {
        return $this->resource($occupancy);
    }

    public function addMembers(AddMembersRequest $request, Occupancy $occupancy): JsonResponse
    {
        return $this->response($this->lifecycle->addMembers($occupancy, $request->validated()), 'Đã thêm thành viên.');
    }

    public function leaveMember(LeaveMemberRequest $request, Occupancy $occupancy, TenantStay $stay): JsonResponse
    {
        return $this->response($this->lifecycle->leaveMember($occupancy, $stay, $request->validated()), 'Đã ghi nhận thành viên rời đi.');
    }

    public function changeRepresentative(ChangeRepresentativeRequest $request, Occupancy $occupancy): JsonResponse
    {
        return $this->response($this->lifecycle->changeRepresentative($occupancy, $request->validated()), 'Đã lưu người đại diện.');
    }

    public function applyService(ApplyServiceRequest $request, Occupancy $occupancy): JsonResponse
    {
        return $this->response($this->lifecycle->applyService($occupancy, $request->validated()), 'Đã lưu khoảng áp dụng dịch vụ.', 201);
    }

    public function endService(EndServiceRequest $request, Occupancy $occupancy, OccupancyService $period): JsonResponse
    {
        return $this->response($this->lifecycle->endService($occupancy, $period, $request->validated()), 'Đã ngừng khoảng áp dụng dịch vụ.');
    }

    public function transfer(TransferOccupancyRequest $request, Occupancy $occupancy): JsonResponse
    {
        return $this->response($this->lifecycle->transfer($occupancy, $request->validated()), 'Đã chuyển phòng và giữ lịch sử đợt trước.', 201);
    }

    public function checkOut(CheckoutRequest $request, Occupancy $occupancy): JsonResponse
    {
        return $this->response($this->lifecycle->checkOut($occupancy, $request->validated()), 'Đã trả phòng. Hóa đơn và lịch sử được giữ nguyên.');
    }

    private function query(): Builder
    {
        return Occupancy::query()->with('room', 'rentalGroup', 'nextOccupancy:id,previous_occupancy_id')
            ->withCount(['tenantStays as resident_count' => fn ($query) => $query->whereNull('moved_out_at')]);
    }

    private function resource(Occupancy $occupancy): OccupancyResource
    {
        return new OccupancyResource($this->query()->with([
            'tenantStays' => fn ($query) => $query->with('tenant')->orderBy('moved_in_at')->orderBy('id'),
            'representativeAssignments' => fn ($query) => $query->with('tenantStay.tenant')->orderBy('started_at')->orderBy('id'),
            'occupancyServices' => fn ($query) => $query->with('service')->orderBy('effective_from')->orderBy('id'),
        ])->findOrFail($occupancy->id));
    }

    private function response(Occupancy $occupancy, string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $this->resource($occupancy)], $status);
    }
}
