<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TenantPortal
{
    public function profile(User $actor): Tenant
    {
        abort_if($actor->tenant_id === null, 404, 'Tài khoản chưa được liên kết với hồ sơ người thuê.');

        return Tenant::query()->findOrFail($actor->tenant_id);
    }

    public function stays(User $actor): Builder
    {
        $tenant = $this->profile($actor);
        $at = now();

        // Never expose the owner's OccupancyResource: it contains other residents.
        return TenantStay::query()->where('tenant_id', $tenant->id)->where('moved_in_at', '<=', $at)
            ->with('occupancy.room', 'occupancy.rentalGroup')
            ->withExists(['representativeAssignments as is_current_representative' => fn ($query) => $query
                ->whereColumn('representative_assignments.occupancy_id', 'tenant_stays.occupancy_id')
                ->whereNull('ended_at')->where('started_at', '<=', $at)]);
    }

    public function currentStay(User $actor): ?TenantStay
    {
        $at = now();

        return $this->stays($actor)->whereNull('moved_out_at')
            ->whereHas('occupancy', fn ($query) => $query->whereNull('ended_at')->where('started_at', '<=', $at))
            ->with(['occupancy.occupancyServices' => fn ($query) => $query->with('service')
                ->where('effective_from', '<=', $at)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $at))
                ->orderBy('service_id')->orderBy('id')])
            ->first();
    }
}
