<?php

namespace App\Services;

use App\Models\CheckoutApplication;
use App\Models\Occupancy;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CheckoutApplications
{
    public function listing(): Builder
    {
        return CheckoutApplication::query()->with(['occupancy.room', 'requesterStay.tenant']);
    }

    public function submit(Occupancy $occupancy, User $actor): CheckoutApplication
    {
        try {
            return DB::transaction(function () use ($occupancy, $actor) {
                $locked = $this->lockActiveOccupancy($occupancy->id);
                $stay = $this->representativeStay($locked, $actor);
                abort_if(CheckoutApplication::query()->where('pending_occupancy_id', $locked->id)->exists(), 409,
                    'Đợt thuê này đã có yêu cầu trả phòng đang chờ xử lý.');

                return CheckoutApplication::create([
                    'occupancy_id' => $locked->id,
                    'requester_stay_id' => $stay->id,
                    'requested_by' => $actor->id,
                    'status' => 'pending',
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Đợt thuê này đã có yêu cầu trả phòng đang chờ xử lý.');
        }
    }

    public function withdraw(CheckoutApplication $application, User $actor): CheckoutApplication
    {
        abort_unless((int) $application->requested_by === $actor->id, 404);

        return DB::transaction(function () use ($application, $actor) {
            $occupancy = $this->lockActiveOccupancy($application->occupancy_id);
            $locked = $application->lockPending();
            $stay = $this->representativeStay($occupancy, $actor);
            abort_unless($stay->id === (int) $locked->requester_stay_id, 403);
            $locked->update(['status' => 'withdrawn']);

            return $locked;
        }, 3);
    }

    public function reject(CheckoutApplication $application, string $reason): CheckoutApplication
    {
        return DB::transaction(function () use ($application, $reason) {
            $this->lockActiveOccupancy($application->occupancy_id);
            $locked = $application->lockPending();
            $locked->update(['status' => 'rejected', 'rejection_reason' => $reason]);

            return $locked;
        }, 3);
    }

    private function lockActiveOccupancy(int $id): Occupancy
    {
        $occupancy = Occupancy::query()->lockForUpdate()->findOrFail($id);
        abort_if($occupancy->ended_at !== null, 409, 'Đợt thuê đã kết thúc.');

        return $occupancy;
    }

    private function representativeStay(Occupancy $occupancy, User $actor): TenantStay
    {
        $at = now();
        $stay = $occupancy->tenantStays()->where('tenant_id', $actor->tenant_id)
            ->whereNull('moved_out_at')->where('moved_in_at', '<=', $at)
            ->whereHas('representativeAssignments', fn ($query) => $query->where('occupancy_id', $occupancy->id)
                ->whereNull('ended_at')->where('started_at', '<=', $at))
            ->first();

        abort_unless($stay, 403, 'Chỉ người đại diện đang cư trú mới có thể gửi hoặc rút yêu cầu trả phòng.');

        return $stay;
    }
}
