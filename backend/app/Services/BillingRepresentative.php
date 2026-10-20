<?php

namespace App\Services;

use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingRepresentative
{
    public function restore(Occupancy $occupancy, int $stayId): RepresentativeAssignment
    {
        return DB::transaction(function () use ($occupancy, $stayId) {
            Room::query()->lockForUpdate()->findOrFail($occupancy->room_id);
            RentalGroup::query()->lockForUpdate()->findOrFail($occupancy->rental_group_id);
            $locked = Occupancy::query()->lockForUpdate()->findOrFail($occupancy->id);
            abort_if($locked->ended_at === null, 409, 'Đợt thuê còn mở; hãy dùng chức năng chọn người đại diện đang ở.');
            abort_if($locked->invoices()->lockForUpdate()->first() !== null, 409, 'Đợt thuê đã có hóa đơn, không được bổ sung hoặc sửa lịch sử đại diện.');
            abort_if($locked->representativeAssignments()->lockForUpdate()->first() !== null, 409, 'Đợt thuê đã có lịch sử đại diện; chức năng này chỉ bổ sung trường hợp chưa từng chọn đại diện.');
            $stay = $locked->tenantStays()->whereKey($stayId)->lockForUpdate()->first();
            if ($stay === null || $stay->moved_out_at === null || $stay->moved_out_at->lessThan($locked->ended_at)
                || ! $stay->moved_in_at->lessThan($locked->ended_at)) {
                throw ValidationException::withMessages(['tenant_stay_id' => 'Cần chọn đúng thành viên còn cư trú tại lúc kết thúc đợt thuê.']);
            }
            Tenant::query()->lockForUpdate()->findOrFail($stay->tenant_id);
            $start = CarbonImmutable::instance($locked->started_at)->max($stay->moved_in_at);
            abort_unless($start->lessThan($locked->ended_at) && $locked->ended_at->lessThanOrEqualTo(now()), 409, 'Khoảng cư trú đã lưu không hợp lệ.');

            // An explicit owner correction, bounded by the selected resident's actual stay.
            // No login account or invoice access is created by this operation.
            return RepresentativeAssignment::create([
                'occupancy_id' => $locked->id, 'tenant_stay_id' => $stay->id,
                'started_at' => $start, 'ended_at' => $locked->ended_at,
            ]);
        }, 3);
    }
}
