<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCatalog
{
    public function create(array $attributes): Service
    {
        try {
            return Service::create($attributes)->refresh();
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['service_code' => 'Mã dịch vụ đã tồn tại.']);
        }
    }

    public function update(Service $service, array $attributes): Service
    {
        try {
            return DB::transaction(function () use ($service, $attributes) {
                $lockedService = Service::query()->lockForUpdate()->findOrFail($service->id);
                // Existing occupancy_services keep their own unit, method and price snapshots.
                $lockedService->update($attributes);

                return $lockedService;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['service_code' => 'Mã dịch vụ đã tồn tại.']);
        }
    }

    public function delete(Service $service): void
    {
        try {
            DB::transaction(function () use ($service) {
                $lockedService = Service::query()->lockForUpdate()->findOrFail($service->id);
                abort_if($lockedService->occupancyServices()->exists(), 409, 'Dịch vụ đã có lịch sử sử dụng nên không thể xóa. Hãy chuyển is_active thành false để ngừng áp dụng mới.');
                $lockedService->delete();
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23503'], true)) {
                abort(409, 'Dịch vụ đang được lịch sử thuê tham chiếu nên không thể xóa.');
            }
            throw $exception;
        }
    }
}
