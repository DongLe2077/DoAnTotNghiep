<?php

namespace App\Services;

use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenancyLifecycle
{
    public function checkIn(array $data): Occupancy
    {
        return $this->transaction(function () use ($data) {
            $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);
            $group = RentalGroup::query()->lockForUpdate()->findOrFail($data['rental_group_id']);
            $at = $this->date($data['started_at']);
            $ids = array_map('intval', $data['tenant_ids']);
            $this->lockTenants($ids);
            $this->assertRoomReady($room, $at, count($ids));
            $overlap = Occupancy::query()->where('rental_group_id', $group->id)
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $at))->exists();
            abort_if($overlap, 409, 'Nhóm đã có đợt thuê đang mở hoặc trùng khoảng thời gian.');
            $this->assertTenantsAvailable($ids, $at);

            $occupancy = Occupancy::create([
                'room_id' => $room->id, 'rental_group_id' => $group->id,
                'started_at' => $at, 'monthly_rent' => $room->default_monthly_rent,
            ]);
            foreach ($ids as $id) {
                TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $id, 'moved_in_at' => $at]);
            }
            if (isset($data['representative_tenant_id'])) {
                $this->assignRepresentative($occupancy, (int) $data['representative_tenant_id'], $at, 'representative_tenant_id');
            }

            return $occupancy;
        });
    }

    public function addMembers(Occupancy $occupancy, array $data): Occupancy
    {
        return $this->transaction(function () use ($occupancy, $data) {
            $locked = $this->lockContext($occupancy);
            $at = $this->date($data['moved_in_at']);
            $this->assertEventTime($locked, $at, 'moved_in_at');
            $ids = array_map('intval', $data['tenant_ids']);
            $this->lockTenants($ids);
            $this->assertTenantsAvailable($ids, $at);
            $count = $locked->tenantStays()->whereNull('moved_out_at')->count();
            $room = Room::findOrFail($locked->room_id);
            abort_unless($room->operational_status === 'available', 409, 'Phòng đang bảo trì, không thể nhận thêm thành viên.');
            abort_if($count + count($ids) > $room->capacity, 409, 'Số người vượt quá sức chứa của phòng.');
            foreach ($ids as $id) {
                TenantStay::create(['occupancy_id' => $locked->id, 'tenant_id' => $id, 'moved_in_at' => $at]);
            }

            return $locked;
        });
    }

    public function leaveMember(Occupancy $occupancy, TenantStay $stay, array $data): Occupancy
    {
        return $this->transaction(function () use ($occupancy, $stay, $data) {
            $locked = $this->lockContext($occupancy);
            $at = $this->date($data['moved_out_at']);
            $this->assertEventTime($locked, $at, 'moved_out_at');
            $active = $locked->tenantStays()->whereNull('moved_out_at')->orderBy('tenant_id')->get();
            $this->lockTenants($active->pluck('tenant_id')->all());
            $member = $locked->tenantStays()->whereKey($stay->id)->lockForUpdate()->firstOrFail();
            abort_if($member->moved_out_at !== null, 409, 'Thành viên đã rời đợt thuê này.');
            abort_if($active->count() <= 1, 409, 'Thành viên cuối cùng cần dùng chức năng trả phòng để kết thúc đợt thuê.');
            $this->assertPositiveInterval($member->moved_in_at, $at, 'moved_out_at');
            $representative = $locked->representativeAssignments()->whereNull('ended_at')->lockForUpdate()->first();
            if ($representative?->tenant_stay_id === $member->id) {
                if (! isset($data['replacement_tenant_id']) || (int) $data['replacement_tenant_id'] === $member->tenant_id) {
                    throw ValidationException::withMessages(['replacement_tenant_id' => 'Cần chọn thành viên còn ở làm người đại diện thay thế.']);
                }
                $this->assignRepresentative($locked, (int) $data['replacement_tenant_id'], $at, 'replacement_tenant_id');
            }
            $member->update(['moved_out_at' => $at]);

            return $locked;
        });
    }

    public function changeRepresentative(Occupancy $occupancy, array $data): Occupancy
    {
        return $this->transaction(function () use ($occupancy, $data) {
            $locked = $this->lockContext($occupancy);
            $at = $this->date($data['started_at']);
            $this->assertEventTime($locked, $at, 'started_at');
            $ids = $locked->tenantStays()->whereNull('moved_out_at')->pluck('tenant_id')->all();
            $this->lockTenants($ids);
            $this->assignRepresentative($locked, (int) $data['tenant_id'], $at);

            return $locked;
        });
    }

    public function applyService(Occupancy $occupancy, array $data): Occupancy
    {
        return $this->transaction(function () use ($occupancy, $data) {
            $locked = $this->lockContext($occupancy);
            $at = $this->date($data['effective_from']);
            $this->assertEventTime($locked, $at, 'effective_from');
            $service = Service::query()->lockForUpdate()->findOrFail($data['service_id']);
            abort_unless($service->is_active, 409, 'Dịch vụ đã ngừng áp dụng mới.');
            $current = $locked->occupancyServices()->where('service_id', $service->id)->whereNull('effective_until')->lockForUpdate()->first();
            if ($current !== null) {
                $this->assertPositiveInterval($current->effective_from, $at, 'effective_from');
                $current->update(['effective_until' => $at]);
            }
            OccupancyService::create([
                'occupancy_id' => $locked->id, 'service_id' => $service->id, 'effective_from' => $at,
                'unit_price' => $data['unit_price'] ?? $service->default_unit_price,
                'unit' => $data['unit'] ?? $service->unit,
                'billing_method' => $data['billing_method'] ?? $service->billing_method,
            ]);

            return $locked;
        });
    }

    public function endService(Occupancy $occupancy, OccupancyService $period, array $data): Occupancy
    {
        return $this->transaction(function () use ($occupancy, $period, $data) {
            $locked = $this->lockContext($occupancy);
            $record = $locked->occupancyServices()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            abort_if($record->effective_until !== null, 409, 'Khoảng áp dụng dịch vụ đã kết thúc.');
            $at = $this->date($data['effective_until']);
            $this->assertEventTime($locked, $at, 'effective_until');
            $this->assertPositiveInterval($record->effective_from, $at, 'effective_until');
            $record->update(['effective_until' => $at]);

            return $locked;
        });
    }

    public function transfer(Occupancy $occupancy, array $data): Occupancy
    {
        return $this->transaction(function () use ($occupancy, $data) {
            abort_if($occupancy->room_id === (int) $data['room_id'], 409, 'Phòng đích phải khác phòng đang ở.');
            $locked = $this->lockContext($occupancy, [(int) $data['room_id']]);
            $at = $this->date($data['transferred_at']);
            $this->assertEventTime($locked, $at, 'transferred_at');
            $members = $locked->tenantStays()->whereNull('moved_out_at')->orderBy('tenant_id')->get();
            abort_if($members->isEmpty(), 409, 'Đợt thuê chưa có thành viên để chuyển phòng.');
            $this->lockTenants($members->pluck('tenant_id')->all());
            $destination = Room::findOrFail($data['room_id']);
            $this->assertRoomReady($destination, $at, $members->count());
            $periods = $locked->occupancyServices()->whereNull('effective_until')->get();
            $representative = $locked->representativeAssignments()->whereNull('ended_at')->with('tenantStay')->first();
            $representativeId = $data['representative_tenant_id'] ?? $representative?->tenantStay?->tenant_id;
            $this->closePeriods($locked, $at);
            $next = Occupancy::create([
                'rental_group_id' => $locked->rental_group_id, 'previous_occupancy_id' => $locked->id,
                'room_id' => $destination->id, 'started_at' => $at, 'monthly_rent' => $locked->monthly_rent,
            ]);
            foreach ($members as $member) {
                TenantStay::create(['occupancy_id' => $next->id, 'tenant_id' => $member->tenant_id, 'moved_in_at' => $at]);
            }
            foreach ($periods as $period) {
                OccupancyService::create([
                    'occupancy_id' => $next->id, 'service_id' => $period->service_id, 'effective_from' => $at,
                    'unit_price' => $period->unit_price, 'billing_method' => $period->billing_method, 'unit' => $period->unit,
                ]);
            }
            if ($representativeId !== null) {
                $this->assignRepresentative($next, (int) $representativeId, $at, 'representative_tenant_id');
            }

            return $next;
        });
    }

    public function checkOut(Occupancy $occupancy, array $data): Occupancy
    {
        return $this->transaction(function () use ($occupancy, $data) {
            $locked = $this->lockContext($occupancy);
            $at = $this->date($data['ended_at']);
            $this->assertEventTime($locked, $at, 'ended_at');
            $this->lockTenants($locked->tenantStays()->whereNull('moved_out_at')->pluck('tenant_id')->all());
            $this->closePeriods($locked, $at);

            return $locked;
        });
    }

    private function lockContext(Occupancy $occupancy, array $extraRoomIds = []): Occupancy
    {
        $ids = array_unique([$occupancy->room_id, ...$extraRoomIds]);
        $rooms = Room::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        abort_unless($rooms->count() === count($ids), 404);
        RentalGroup::query()->lockForUpdate()->findOrFail($occupancy->rental_group_id);
        $locked = Occupancy::query()->lockForUpdate()->findOrFail($occupancy->id);
        abort_if($locked->ended_at !== null, 409, 'Đợt thuê đã kết thúc; lịch sử không được sửa trực tiếp.');

        return $locked;
    }

    private function lockTenants(array $ids): void
    {
        $ids = array_unique(array_map('intval', $ids));
        $tenants = Tenant::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        if ($tenants->count() !== count($ids)) {
            throw ValidationException::withMessages(['tenant_ids' => 'Một số hồ sơ người thuê không còn tồn tại.']);
        }
    }

    private function assertRoomReady(Room $room, CarbonImmutable $at, int $residents): void
    {
        abort_unless($room->operational_status === 'available', 409, 'Phòng đang bảo trì, không thể nhận người thuê.');
        abort_if($residents > $room->capacity, 409, 'Số người vượt quá sức chứa của phòng.');
        $overlap = Occupancy::query()->where('room_id', $room->id)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $at))->exists();
        abort_if($overlap, 409, 'Phòng đã có đợt thuê đang mở hoặc trùng khoảng thời gian.');
    }

    private function assertTenantsAvailable(array $ids, CarbonImmutable $at): void
    {
        $overlap = TenantStay::query()->whereIn('tenant_id', $ids)
            ->where(fn ($query) => $query->whereNull('moved_out_at')->orWhere('moved_out_at', '>', $at))
            ->orderBy('tenant_id')->lockForUpdate()->first();
        abort_if($overlap !== null, 409, 'Một người đã có lịch sử cư trú trùng thời gian hoặc vẫn đang ở đợt thuê khác.');
    }

    private function assignRepresentative(Occupancy $occupancy, int $tenantId, CarbonImmutable $at, string $field = 'tenant_id'): void
    {
        $member = $occupancy->tenantStays()->where('tenant_id', $tenantId)->whereNull('moved_out_at')->lockForUpdate()->first();
        if ($member === null) {
            throw ValidationException::withMessages([$field => 'Người đại diện phải đang ở trong đúng đợt thuê.']);
        }
        $user = User::query()->where('tenant_id', $tenantId)->lockForUpdate()->first();
        if ($user === null || ! $user->is_active || ! $user->hasRole(User::ROLE_TENANT)) {
            throw ValidationException::withMessages([$field => 'Người đại diện cần có tài khoản người thuê đang hoạt động.']);
        }
        $current = $occupancy->representativeAssignments()->whereNull('ended_at')->lockForUpdate()->first();
        if ($current?->tenant_stay_id === $member->id) {
            return;
        }
        if ($current !== null) {
            $this->assertPositiveInterval($current->started_at, $at, 'started_at');
            $current->update(['ended_at' => $at]);
        }
        RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $member->id, 'started_at' => $at]);
    }

    private function closePeriods(Occupancy $occupancy, CarbonImmutable $at): void
    {
        $this->assertPositiveInterval($occupancy->started_at, $at, 'ended_at');
        foreach ($occupancy->tenantStays()->whereNull('moved_out_at')->lockForUpdate()->get() as $stay) {
            $this->assertPositiveInterval($stay->moved_in_at, $at, 'ended_at');
            $stay->update(['moved_out_at' => $at]);
        }
        foreach ($occupancy->representativeAssignments()->whereNull('ended_at')->lockForUpdate()->get() as $assignment) {
            $this->assertPositiveInterval($assignment->started_at, $at, 'ended_at');
            $assignment->update(['ended_at' => $at]);
        }
        foreach ($occupancy->occupancyServices()->whereNull('effective_until')->lockForUpdate()->get() as $period) {
            $this->assertPositiveInterval($period->effective_from, $at, 'ended_at');
            $period->update(['effective_until' => $at]);
        }
        // Historical invoices and their explicit per-invoice access grants stay intact.
        $occupancy->update(['ended_at' => $at]);
    }

    private function assertEventTime(Occupancy $occupancy, CarbonImmutable $at, string $field): void
    {
        $latest = $occupancy->started_at->format('Y-m-d H:i:s');
        foreach ([
            'tenant_stays' => ['moved_in_at', 'moved_out_at'],
            'representative_assignments' => ['started_at', 'ended_at'],
            'occupancy_services' => ['effective_from', 'effective_until'],
            'invoices' => ['issued_at'],
            'invoice_items' => ['charge_period_start', 'charge_period_end'],
            'maintenance_requests' => ['submitted_at'],
        ] as $table => $columns) {
            $selects = array_map(fn ($column) => "MAX({$column}) AS {$column}", $columns);
            $row = (array) DB::table($table)->where('occupancy_id', $occupancy->id)->selectRaw(implode(', ', $selects))->first();
            foreach ($row as $value) {
                if ($value !== null && $value > $latest) {
                    $latest = $value;
                }
            }
        }
        $readingEnd = DB::table('meter_readings as readings')->join('occupancy_services as periods', 'periods.id', '=', 'readings.occupancy_service_id')
            ->where('periods.occupancy_id', $occupancy->id)->max('readings.period_end');
        $latest = max($latest, $readingEnd ?? $latest);
        if ($at->format('Y-m-d H:i:s') < $latest) {
            throw ValidationException::withMessages([$field => "Thời điểm thay đổi phải từ {$latest} trở đi để giữ đúng lịch sử đã ghi."]);
        }
    }

    private function assertPositiveInterval(mixed $start, CarbonImmutable $end, string $field): void
    {
        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([$field => 'Thời điểm kết thúc phải sau thời điểm bắt đầu của các khoảng đang áp dụng.']);
        }
    }

    private function date(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $value, config('app.timezone'));
    }

    private function transaction(Closure $callback): mixed
    {
        try {
            return DB::transaction($callback, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Dữ liệu thuê vừa thay đổi hoặc trùng dữ liệu đang hiệu lực. Vui lòng tải lại và thử lại.');
        }
    }
}
