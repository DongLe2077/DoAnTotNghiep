<?php

namespace App\Services\Marketplace;

use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\ScheduledDeletion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyWorkspaces;
use App\Services\TenancyLifecycle;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

class RoomRequests
{
    public function __construct(
        private readonly RoomListings $listings,
        private readonly PropertyContext $context,
        private readonly PropertyWorkspaces $workspaces,
        private readonly TenancyLifecycle $tenancy,
    ) {}

    public function create(User $user, int $listingId, array $data): array
    {
        $propertyId = (int) DB::table('room_listings')->where('id', $listingId)->value('property_id');
        abort_unless($propertyId, 404);

        return $this->transaction($propertyId, function (stdClass $property) use ($user, $listingId, $propertyId, $data) {
            abort_if((int) $property->owner_user_id === $user->id, 409, 'Bạn đang quản lý phòng này.');
            $listing = $this->lockListing($propertyId, $listingId);
            $this->listings->availableUnderLock($listing, $data['people']);
            $key = $listingId.':'.$user->id.':'.$data['kind'];
            $existing = DB::table('room_requests')->where('open_key', $key)->first();
            if ($existing) {
                abort(409, 'Bạn đã có yêu cầu đang xử lý cho tin này. Mở Yêu cầu của tôi để xem.');
            }
            $status = $data['kind'] === 'viewing' ? 'pending' : 'new';
            $id = DB::table('room_requests')->insertGetId($data + [
                'listing_id' => $listingId, 'property_id' => $propertyId, 'user_id' => $user->id,
                'status' => $status, 'open_key' => $key,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->recordEvent($id, $user->id, $status);
            app(\App\Services\LiveUpdates::class)->user($user->id);

            return $this->data($this->query()->where('q.id', $id)->first());
        });
    }

    public function forUser(User $user): array
    {
        return RoomListings::page($this->query()->where('q.user_id', $user->id)->latest('q.id')->paginate(12), $this->data(...));
    }

    public function forOwner(int $propertyId, array $filters): array
    {
        $query = $this->query()
            ->leftJoin('tenants as profile', fn ($join) => $join->on('profile.id', '=', 'm.tenant_id')
                ->on('profile.property_id', '=', 'q.property_id'))
            ->addSelect('profile.full_name as tenant_name')
            ->where('q.property_id', $propertyId);
        foreach (['kind', 'status'] as $field) {
            if (isset($filters[$field])) {
                $query->where('q.'.$field, $filters[$field]);
            }
        }

        return RoomListings::page($query->latest('q.id')->paginate(12), fn ($row) => $this->data($row) + [
            'tenant_id' => $row->tenant_id === null ? null : (int) $row->tenant_id,
            'tenant_name' => $row->tenant_name,
            'is_member' => $row->membership_status === 'approved' && $row->membership_revoked_at === null,
        ]);
    }

    public function userAction(User $user, int $id, string $action): array
    {
        $propertyId = (int) DB::table('room_requests')->where('id', $id)->where('user_id', $user->id)->value('property_id');
        abort_unless($propertyId, 404);

        return $this->transaction($propertyId, function () use ($user, $id, $action, $propertyId) {
            $row = $this->lockRequest($propertyId, $id);
            abort_unless((int) $row->user_id === $user->id, 404);
            if ($action === 'accept_time') {
                abort_unless($row->kind === 'viewing' && $row->status === 'proposed', 409, 'Không có giờ mới để xác nhận.');
                abort_unless($row->appointment_at > now()->toDateTimeString(), 409, 'Giờ đề xuất đã qua.');
                $this->listings->availableUnderLock($this->lockListing($propertyId, (int) $row->listing_id), (int) $row->people);
                $this->update($row, $user->id, 'confirmed');
            } else {
                abort_unless($row->open_key !== null, 409, 'Yêu cầu đã kết thúc.');
                $this->update($row, $user->id, $row->kind === 'viewing' ? 'cancelled' : 'withdrawn');
            }

            return $this->data($this->query()->where('q.id', $id)->first());
        });
    }

    public function ownerAction(User $owner, int $propertyId, int $id, array $data): array
    {
        return $this->transaction($propertyId, function () use ($owner, $propertyId, $id, $data) {
            $row = $this->lockRequest($propertyId, $id);
            $action = $data['action'];
            $extra = ['handled_by' => $owner->id, 'owner_note' => $data['owner_note'] ?? $row->owner_note];
            if ($row->kind === 'viewing') {
                $status = $this->viewingStatus($row, $action);
                if (in_array($action, ['confirm', 'propose'], true)) {
                    $appointment = $action === 'propose' ? ($data['appointment_at'] ?? null) : $row->preferred_at;
                    abort_unless($appointment && $appointment > now()->toDateTimeString(), 422, 'Hãy chọn ngày giờ xem trong tương lai.');
                    $this->listings->availableUnderLock($this->lockListing($propertyId, (int) $row->listing_id), (int) $row->people);
                    $extra['appointment_at'] = $appointment;
                }
                if (in_array($action, ['viewed', 'no_show'], true)) {
                    abort_unless($row->appointment_at && $row->appointment_at <= now()->toDateTimeString(), 409, 'Chỉ ghi kết quả sau giờ hẹn.');
                }
            } else {
                $status = $this->rentalStatus($row, $action);
                if ($action === 'accept') {
                    $this->listings->availableUnderLock($this->lockListing($propertyId, (int) $row->listing_id), (int) $row->people);
                }
            }
            $this->update($row, $owner->id, $status, $extra);

            return $this->data($this->query()->where('q.id', $id)->first());
        });
    }

    public function moveIn(User $owner, int $propertyId, int $id, array $data): array
    {
        return $this->transaction($propertyId, function () use ($owner, $propertyId, $id, $data) {
            $row = $this->lockRequest($propertyId, $id);
            if ($row->status === 'moved_in') {
                return $this->data($this->query()->where('q.id', $id)->first());
            }
            abort_unless($row->kind === 'rent' && $row->status === 'accepted', 409, 'Hãy chấp thuận yêu cầu thuê trước.');
            $listing = $this->lockListing($propertyId, (int) $row->listing_id);
            [$room, $openStay] = $this->listings->availableUnderLock($listing, (int) $row->people);

            $user = User::query()->where('is_active', true)->find($row->user_id);
            abort_unless($user && $user->id !== $owner->id, 409, 'Tài khoản người thuê không còn hoạt động.');
            $member = DB::table('property_memberships')->where('property_id', $propertyId)->where('user_id', $user->id)->lockForUpdate()->first();
            $approve = ! $member || $member->status !== 'approved' || $member->revoked_at !== null;
            abort_if($approve && empty($data['approve_membership']), 422, 'Cần xác nhận duyệt người thuê vào khu trong lần nhận phòng này.');

            $linked = $member?->tenant_id;
            $chosen = $data['tenant_id'] ?? $linked;
            abort_if($linked !== null && (int) $chosen !== (int) $linked, 409, 'Tài khoản đã liên kết với hồ sơ khác.');
            $tenant = $chosen ? Tenant::query()->lockForUpdate()->findOrFail($chosen) : Tenant::create([
                'full_name' => $row->contact_name, 'phone_number' => $row->contact_phone,
            ]);
            $companions = array_map('intval', $data['companion_ids'] ?? []);
            abort_if(count($companions) + 1 !== (int) $row->people || in_array($tenant->id, $companions, true),
                422, 'Chọn đúng số hồ sơ người đi cùng, không lặp hồ sơ người gửi yêu cầu.');
            foreach ([$tenant->id, ...$companions] as $tenantId) {
                abort_if(ScheduledDeletion::query()->whereJsonContains('records->tenants', $tenantId)->exists(),
                    409, 'Có hồ sơ người thuê đang chờ xóa.');
            }

            if ($approve) {
                DB::table('property_memberships')->updateOrInsert(['property_id' => $propertyId, 'user_id' => $user->id], [
                    'role' => 'member', 'status' => 'approved', 'approved_by' => $owner->id, 'approved_at' => now(),
                    'revoked_at' => null, 'updated_at' => now(), ...($member ? [] : ['created_at' => now()]),
                ]);
                // Approval here is explicit and recorded on the rental request, without spending an invitation.
                DB::table('property_join_requests')->where('property_id', $propertyId)->where('user_id', $user->id)
                    ->where('status', 'pending')->update([
                        'status' => 'approved', 'pending_key' => null, 'decided_by' => $owner->id,
                        'decided_at' => now(), 'updated_at' => now(),
                    ]);
            }
            $this->workspaces->linkTenant($owner, $propertyId, $user->id, $tenant->id);
            $people = ['tenant_ids' => [$tenant->id, ...$companions]];

            if ($openStay) {
                $occupancy = $this->tenancy->addMembers(Occupancy::query()->findOrFail($openStay), $people + ['moved_in_at' => $data['started_at']]);
            } else {
                $group = RentalGroup::create(['group_code' => 'WEB-'.strtoupper(bin2hex(random_bytes(8)))]);
                $occupancy = $this->tenancy->checkIn($people + [
                    'room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => $data['started_at'],
                    'representative_tenant_id' => $tenant->id,
                ]);
            }
            $this->update($row, $owner->id, 'moved_in', [
                'occupancy_id' => $occupancy->id, 'handled_by' => $owner->id,
                'moved_in_at' => $data['started_at'], 'membership_approved_at_move_in' => $approve,
            ]);

            return $this->data($this->query()->where('q.id', $id)->first());
        });
    }

    private function viewingStatus(stdClass $row, string $action): string
    {
        $next = match ($action) {
            'confirm' => $row->status === 'pending' ? 'confirmed' : null,
            'propose' => $row->open_key !== null ? 'proposed' : null,
            'cancel' => $row->open_key !== null ? 'cancelled' : null,
            'viewed', 'no_show' => $row->status === 'confirmed' ? $action : null,
            default => null,
        };
        abort_unless($next, 409, 'Trạng thái lịch đã thay đổi. Hãy tải lại danh sách.');

        return $next;
    }

    private function rentalStatus(stdClass $row, string $action): string
    {
        $next = match ($action) {
            'discuss' => $row->status === 'new' ? 'discussing' : null,
            'accept' => in_array($row->status, ['new', 'discussing'], true) ? 'accepted' : null,
            'reject' => $row->open_key !== null ? 'rejected' : null,
            default => null,
        };
        abort_unless($next, 409, 'Trạng thái yêu cầu đã thay đổi. Hãy tải lại danh sách.');

        return $next;
    }

    private function transaction(int $propertyId, callable $work): mixed
    {
        return $this->context->run($propertyId, fn () => DB::transaction(function () use ($propertyId, $work) {
            $property = DB::table('rental_properties')->where('id', $propertyId)->lockForUpdate()->first();
            abort_unless($property, 404);

            return $work($property);
        }, 3));
    }

    private function lockListing(int $propertyId, int $id): stdClass
    {
        $listing = DB::table('room_listings')->where('property_id', $propertyId)->where('id', $id)->lockForUpdate()->first();
        abort_unless($listing, 404);

        return $listing;
    }

    private function lockRequest(int $propertyId, int $id): stdClass
    {
        $row = DB::table('room_requests')->where('property_id', $propertyId)->where('id', $id)->lockForUpdate()->first();
        abort_unless($row, 404);

        return $row;
    }

    private function update(stdClass $row, int $actorId, string $status, array $extra = []): void
    {
        $active = in_array($status, ['pending', 'confirmed', 'proposed', 'new', 'discussing', 'accepted'], true);
        DB::table('room_requests')->where('id', $row->id)->update($extra + [
            'status' => $status, 'open_key' => $active ? $row->open_key : null, 'updated_at' => now(),
        ]);
        app(\App\Services\LiveUpdates::class)->user((int) $row->user_id);
        $this->recordEvent((int) $row->id, $actorId, $status, $extra['appointment_at'] ?? $row->appointment_at);
    }

    private function recordEvent(int $requestId, int $actorId, string $status, ?string $appointment = null): void
    {
        DB::table('room_request_events')->insert([
            'request_id' => $requestId, 'actor_id' => $actorId, 'status' => $status,
            'appointment_at' => $appointment, 'occurred_at' => now(),
        ]);
    }

    private function query(): Builder
    {
        return DB::table('room_requests as q')
            ->join('room_listings as l', fn ($join) => $join->on('l.id', '=', 'q.listing_id')->on('l.property_id', '=', 'q.property_id'))
            ->leftJoin('property_memberships as m', fn ($join) => $join->on('m.user_id', '=', 'q.user_id')->on('m.property_id', '=', 'q.property_id'))
            ->select(['q.*', 'l.title', 'm.tenant_id', 'm.status as membership_status', 'm.revoked_at as membership_revoked_at']);
    }

    private function data(stdClass $row): array
    {
        return [
            'id' => (int) $row->id, 'listing_id' => (int) $row->listing_id, 'title' => $row->title,
            'kind' => $row->kind, 'status' => $row->status, 'people' => (int) $row->people,
            'contact_name' => $row->contact_name, 'contact_phone' => $row->contact_phone,
            'note' => $row->note, 'owner_note' => $row->owner_note,
            'preferred_at' => $row->preferred_at, 'appointment_at' => $row->appointment_at,
            'occupancy_id' => $row->occupancy_id === null ? null : (int) $row->occupancy_id,
            'created_at' => $row->created_at, 'moved_in_at' => $row->moved_in_at,
        ];
    }
}
