<?php

namespace App\Services;

use App\Models\MaintenanceRequest;
use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MaintenanceManagement
{
    public function __construct(private readonly AppNotifications $notifications) {}

    public function query(array $filters = [], ?int $authorId = null): Builder
    {
        $query = MaintenanceRequest::query()->with('occupancy.room', 'occupancy.rentalGroup', 'photos');
        if ($authorId !== null) {
            $query->where('submitted_by', $authorId);
        } else {
            $query->with('submittedBy.tenant');
            if (isset($filters['submitted_by'])) {
                $query->where('submitted_by', $filters['submitted_by']);
            }
        }
        foreach (['occupancy_id', 'status'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['room_id', 'rental_group_id'] as $field) {
            if (isset($filters[$field])) {
                $query->whereHas('occupancy', fn ($query) => $query->where($field, $filters[$field]));
            }
        }
        if (isset($filters['from'])) {
            $query->where('submitted_at', '>=', $filters['from']);
        }
        if (isset($filters['until'])) {
            $query->where('submitted_at', '<=', $filters['until']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($query) => $query->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("resolution LIKE ? ESCAPE '!'", [$pattern]));
        }

        return $query;
    }

    public function eligibleOccupancies(int $actorId): Builder
    {
        $at = now();

        return Occupancy::query()->whereNull('ended_at')->where('started_at', '<=', $at)
            ->whereHas('representativeAssignments', fn ($query) => $query->whereNull('ended_at')->where('started_at', '<=', $at)
                ->whereHas('tenantStay', fn ($query) => $query->whereColumn('tenant_stays.occupancy_id', 'representative_assignments.occupancy_id')
                    ->whereNull('moved_out_at')->where('moved_in_at', '<=', $at)
                    ->whereHas('tenant.user', fn ($query) => $query->whereKey($actorId)->where('role', User::ROLE_TENANT)->where('is_active', true))))
            ->with('room:id,room_number', 'rentalGroup:id,group_code')->orderBy('id');
    }

    /** @return array{request: MaintenanceRequest, replayed: bool} */
    public function submit(int|string $occupancyId, array $data, User $actor): array
    {
        // Initial scoping hides foreign/closed occupancies; the same checks run again under locks.
        $context = $this->eligibleOccupancies($actor->id)->findOrFail($occupancyId);

        return DB::transaction(function () use ($context, $data, $actor) {
            $occupancy = $this->lockContext($context);
            $at = now()->startOfSecond();
            abort_if($occupancy->ended_at !== null || $occupancy->started_at->greaterThan($at), 404);
            abort_if($actor->tenant_id === null, 404);
            Tenant::query()->lockForUpdate()->findOrFail($actor->tenant_id);
            $account = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless($account->is_active && $account->hasRole(User::ROLE_TENANT), 401, 'Tài khoản không còn quyền gửi báo hỏng.');
            abort_unless($account->tenant_id === $actor->tenant_id, 404);
            $stay = $occupancy->tenantStays()->where('tenant_id', $account->tenant_id)->whereNull('moved_out_at')
                ->where('moved_in_at', '<=', $at)->lockForUpdate()->first();
            abort_if($stay === null, 404);
            $representative = RepresentativeAssignment::query()->where('occupancy_id', $occupancy->id)
                ->where('tenant_stay_id', $stay->id)->whereNull('ended_at')->where('started_at', '<=', $at)->lockForUpdate()->first();
            abort_if($representative === null, 404);
            $open = $occupancy->maintenanceRequests()->where('submitted_by', $account->id)
                ->whereIn('status', ['submitted', 'in_progress'])->orderBy('id')->lockForUpdate()->get();
            $existing = $open->first(fn ($report) => $report->title === $data['title'] && $report->description === $data['description']);
            if ($existing !== null) {
                return ['request' => $existing, 'replayed' => true];
            }
            $report = MaintenanceRequest::create([
                'occupancy_id' => $occupancy->id, 'submitted_by' => $account->id,
                'representative_assignment_id' => $representative->id,
                'title' => $data['title'], 'description' => $data['description'],
                'status' => 'submitted', 'resolution' => null,
            ]);
            $this->notifications->maintenanceSubmitted($report);

            return ['request' => $report->refresh(), 'replayed' => false];
        }, 3);
    }

    public function process(MaintenanceRequest $report, array $data): MaintenanceRequest
    {
        return DB::transaction(function () use ($report, $data) {
            $this->lockContext(Occupancy::query()->findOrFail($report->occupancy_id));
            $locked = MaintenanceRequest::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless(hash_equals(self::version($locked), $data['version_token']), 409, 'Yêu cầu đã thay đổi. Hãy tải lại trước khi cập nhật xử lý.');
            abort_if($locked->status === 'resolved', 409, 'Yêu cầu đã hoàn tất; kết quả được giữ nguyên. Sự cố mới cần gửi yêu cầu mới.');
            abort_unless(in_array($locked->status, ['submitted', 'in_progress'], true), 409, 'Trạng thái đã lưu không hợp lệ.');
            $values = ['status' => $data['status']];
            if (array_key_exists('resolution', $data)) {
                $values['resolution'] = $data['resolution'];
            }
            $locked->fill($values);
            if (! $locked->isDirty()) {
                return $locked;
            }
            $locked->revision++;
            $locked->save();
            $this->notifications->maintenanceUpdated($locked);

            return $locked->refresh();
        }, 3);
    }

    public static function version(MaintenanceRequest $report): string
    {
        return hash_hmac('sha256', json_encode([
            $report->id, $report->occupancy_id, $report->submitted_by, $report->representative_assignment_id,
            $report->title, $report->description, $report->status, $report->resolution,
            $report->submitted_at->format('Y-m-d H:i:s'), $report->updated_at->format('Y-m-d H:i:s'),
            $report->revision,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), config('app.key'));
    }

    private function lockContext(Occupancy $occupancy): Occupancy
    {
        Room::query()->lockForUpdate()->findOrFail($occupancy->room_id);
        RentalGroup::query()->lockForUpdate()->findOrFail($occupancy->rental_group_id);

        return Occupancy::query()->lockForUpdate()->findOrFail($occupancy->id);
    }
}
