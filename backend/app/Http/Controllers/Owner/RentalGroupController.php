<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RentalGroupIndexRequest;
use App\Http\Requests\Owner\SaveRentalGroupRequest;
use App\Http\Resources\RentalGroupResource;
use App\Models\RentalGroup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RentalGroupController extends Controller
{
    public function index(RentalGroupIndexRequest $request): AnonymousResourceCollection
    {
        $query = RentalGroup::query()->withCount('occupancies');
        if ($request->filled('q')) {
            $query->where('group_code', 'like', '%'.$request->validated('q').'%');
        }

        return RentalGroupResource::collection($query->orderBy('group_code')->orderBy('id')->paginate($request->validated('per_page', 15))->withQueryString());
    }

    public function store(SaveRentalGroupRequest $request): JsonResponse
    {
        try {
            $group = RentalGroup::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['group_code' => 'Mã nhóm thuê đã tồn tại.']);
        }

        return response()->json(['message' => 'Đã tạo nhóm thuê.', 'data' => new RentalGroupResource($group->loadCount('occupancies'))], 201);
    }

    public function show(RentalGroup $rentalGroup): RentalGroupResource
    {
        return new RentalGroupResource($rentalGroup->loadCount('occupancies'));
    }

    public function update(SaveRentalGroupRequest $request, RentalGroup $rentalGroup): JsonResponse
    {
        try {
            $group = DB::transaction(function () use ($request, $rentalGroup) {
                $locked = RentalGroup::query()->lockForUpdate()->findOrFail($rentalGroup->id);
                $locked->update($request->validated());

                return $locked;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['group_code' => 'Mã nhóm thuê đã tồn tại.']);
        }

        return response()->json(['message' => 'Đã cập nhật nhóm thuê.', 'data' => new RentalGroupResource($group->loadCount('occupancies'))]);
    }

    public function destroy(RentalGroup $rentalGroup): Response
    {
        DB::transaction(function () use ($rentalGroup) {
            $locked = RentalGroup::query()->lockForUpdate()->findOrFail($rentalGroup->id);
            abort_if($locked->occupancies()->exists(), 409, 'Nhóm đã có lịch sử thuê nên không thể xóa.');
            $locked->delete();
        }, 3);

        return response()->noContent();
    }
}
