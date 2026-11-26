<?php

namespace App\Services\Tenancy;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class PropertyOnboarding
{
    public function __construct(private readonly PropertyAccess $access) {}

    public function invitations(User $owner, int $propertyId, int $page): array
    {
        $this->access->owner($owner, $propertyId);
        $rows = DB::table('property_invitations')->where('property_id', $propertyId)->latest('id')->paginate(12, page: $page);

        return $this->page($rows, $this->invitationData(...));
    }

    public function issue(User $owner, int $propertyId, int $days, int $limit, ?int $replace = null): array
    {
        return $this->lockedProperty($propertyId, function () use ($owner, $propertyId, $days, $limit, $replace) {
            $this->access->owner($owner, $propertyId);
            if ($replace !== null) {
                $old = $this->invitation($propertyId, $replace);
                abort_if($old->revoked_at !== null, 409, 'Mã mời này đã được thu hồi hoặc đổi.');
                DB::table('property_invitations')->where('id', $old->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            }

            $code = strtoupper(bin2hex(random_bytes(12)));
            $id = DB::table('property_invitations')->insertGetId([
                'property_id' => $propertyId,
                'code_hash' => hash('sha256', $code),
                'code_suffix' => substr($code, -4),
                'max_approvals' => $limit,
                'expires_at' => now()->addDays($days),
                'created_by' => $owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->invitationData($this->invitation($propertyId, $id)) + ['code' => implode('-', str_split($code, 4))];
        });
    }

    public function revokeInvite(User $owner, int $propertyId, int $id): void
    {
        $this->lockedProperty($propertyId, function () use ($owner, $propertyId, $id) {
            $this->access->owner($owner, $propertyId);
            $this->invitation($propertyId, $id);
            DB::table('property_invitations')->where('id', $id)->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);
        });
    }

    public function preview(string $code): array
    {
        $invite = $this->findCode($code);
        $this->requireUsable($invite);
        $property = DB::table('rental_properties')->where('id', $invite->property_id)->first(['name', 'owner_user_id']);
        abort_unless($property?->owner_user_id, 422, 'Mã mời không hợp lệ.');

        return ['property_name' => $property->name];
    }

    public function requestJoin(User $user, string $code): array
    {
        $invite = $this->findCode($code);
        $propertyId = (int) $invite->property_id;

        return $this->lockedProperty($propertyId, function (stdClass $property) use ($user, $invite, $propertyId) {
            $invite = $this->invitation($propertyId, (int) $invite->id);
            $this->requireUsable($invite);
            abort_unless($property->owner_user_id, 422, 'Mã mời không hợp lệ.');
            $member = $this->membership($propertyId, $user->id)->first();
            abort_if($member && $member->status === 'approved' && $member->revoked_at === null, 409, 'Bạn đã là thành viên khu trọ này.');

            $rejectedWithThisCode = DB::table('property_join_requests')->where('property_id', $propertyId)
                ->where('user_id', $user->id)->where('invitation_id', $invite->id)->where('status', 'rejected')->exists();
            abort_if($rejectedWithThisCode, 409, 'Yêu cầu dùng mã này đã bị từ chối. Hãy liên hệ chủ trọ nếu bạn cần gửi lại.');

            $key = $propertyId.':'.$user->id;
            $pending = DB::table('property_join_requests')->where('pending_key', $key)->lockForUpdate()->first();
            if ($pending) {
                $prior = $this->invitation($propertyId, (int) $pending->invitation_id);
                // A new valid code can repair a waiting request with an unusable invitation.
                if ((int) $prior->id !== (int) $invite->id && $this->invitationStatus($prior) !== 'active') {
                    DB::table('property_join_requests')->where('id', $pending->id)
                        ->update(['invitation_id' => $invite->id, 'updated_at' => now()]);
                }

                return ['created' => false, 'data' => $this->ownRequest($user->id, (int) $pending->id)];
            }

            $id = DB::table('property_join_requests')->insertGetId([
                'property_id' => $propertyId, 'invitation_id' => $invite->id, 'user_id' => $user->id,
                'pending_key' => $key, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['created' => true, 'data' => $this->ownRequest($user->id, $id)];
        });
    }

    public function ownRequests(User $user, int $page): array
    {
        $rows = $this->requests()->where('j.user_id', $user->id)->latest('j.id')->paginate(12, page: $page);

        return $this->page($rows, $this->requestData(...));
    }

    public function withdraw(User $user, int $id): array
    {
        $propertyId = DB::table('property_join_requests')->where('id', $id)->where('user_id', $user->id)->value('property_id');
        abort_unless($propertyId, 404);

        return $this->lockedProperty((int) $propertyId, function () use ($user, $id) {
            $row = DB::table('property_join_requests')->where('id', $id)->where('user_id', $user->id)->lockForUpdate()->first();
            abort_unless($row, 404);
            $this->close($row, 'withdrawn', $user->id);

            return $this->ownRequest($user->id, $id);
        });
    }

    public function ownerRequests(User $owner, int $propertyId, string $status, int $page): array
    {
        $this->access->owner($owner, $propertyId);
        $rows = $this->requests()->where('j.property_id', $propertyId)
            ->when($status !== 'all', fn ($query) => $query->where('j.status', $status))
            ->latest('j.id')->paginate(12, page: $page);

        return $this->page($rows, $this->ownerRequestData(...)) + [
            'pending_count' => DB::table('property_join_requests')->where('property_id', $propertyId)->where('status', 'pending')->count(),
        ];
    }

    public function decide(User $owner, int $propertyId, int $id, string $decision): array
    {
        return $this->lockedProperty($propertyId, function () use ($owner, $propertyId, $id, $decision) {
            $this->access->owner($owner, $propertyId);
            $row = DB::table('property_join_requests')->where('property_id', $propertyId)->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->status === $decision) {
                return $this->ownerRequestData($this->ownerRequest($propertyId, $id));
            }
            abort_unless($row->status === 'pending', 409, 'Yêu cầu này đã được xử lý.');
            if ($decision === 'approved') {
                $invite = $this->invitation($propertyId, (int) $row->invitation_id);
                $this->requireUsable($invite, 409);
                abort_unless(DB::table('users')->where('id', $row->user_id)->where('is_active', true)->exists(), 409, 'Tài khoản này không còn hoạt động.');
                $member = $this->membership($propertyId, (int) $row->user_id)->first();
                abort_if($member && $member->status === 'approved' && $member->revoked_at === null, 409, 'Tài khoản đã là thành viên khu trọ.');
                DB::table('property_memberships')->updateOrInsert(
                    ['property_id' => $propertyId, 'user_id' => $row->user_id],
                    ['role' => 'member', 'status' => 'approved', 'approved_by' => $owner->id, 'approved_at' => now(),
                        'revoked_at' => null, 'updated_at' => now(), ...($member ? [] : ['created_at' => now()])],
                );
                DB::table('property_invitations')->where('id', $invite->id)->increment('approvals', 1, ['updated_at' => now()]);
            }

            $this->close($row, $decision, $owner->id);

            return $this->ownerRequestData($this->ownerRequest($propertyId, $id));
        });
    }

    public function revokeMember(User $owner, int $propertyId, int $userId): void
    {
        $this->lockedProperty($propertyId, function () use ($owner, $propertyId, $userId) {
            $this->access->owner($owner, $propertyId);
            abort_if($userId === $owner->id, 409, 'Không thể thu hồi quyền của chủ khu trọ.');
            app(\App\Services\LiveUpdates::class)->user($userId);
            $query = $this->membership($propertyId, $userId);
            $member = $query->first();
            abort_unless($member && in_array($member->status, ['approved', 'revoked'], true), 404);
            if ($member->status === 'revoked') {
                return;
            }
            $now = now();
            $query->update(['status' => 'revoked', 'revoked_at' => $now, 'updated_at' => $now]);

            // Retain accounting history and require a fresh grant if this person rejoins.
            $grants = app(PropertyQueries::class)->scoped($propertyId, 'invoice_access_grants')
                ->where('user_id', $userId)->whereNull('revoked_at');
            (clone $grants)->where('granted_at', '<=', $now)->update(['revoked_at' => $now]);
            $grants->where('granted_at', '>', $now)->update(['revoked_at' => DB::raw('granted_at')]);
        });
    }

    private function lockedProperty(int $propertyId, callable $action): mixed
    {
        // Join, approval and code rotation serialize on the same row, including the last approval slot.
        return DB::transaction(function () use ($propertyId, $action) {
            $property = DB::table('rental_properties')->where('id', $propertyId)->lockForUpdate()->first();
            abort_unless($property, 404);

            return app(PropertyContext::class)->run($propertyId, fn () => $action($property));
        }, 3);
    }

    private function membership(int $propertyId, int $userId): Builder
    {
        return DB::table('property_memberships')->where('property_id', $propertyId)->where('user_id', $userId);
    }

    private function invitation(int $propertyId, int $id): stdClass
    {
        $row = DB::table('property_invitations')->where('property_id', $propertyId)->where('id', $id)->first();
        abort_unless($row, 404);

        return $row;
    }

    private function findCode(string $code): stdClass
    {
        $normalized = strtoupper(preg_replace('/[\s-]/', '', $code));
        if (preg_match('/^[0-9A-F]{24}$/D', $normalized)) {
            $invite = DB::table('property_invitations')->where('code_hash', hash('sha256', $normalized))->first();
            if ($invite) {
                return $invite;
            }
        }

        throw ValidationException::withMessages(['code' => 'Không tìm thấy mã mời. Kiểm tra lại mã hoặc xin chủ trọ cấp mã mới.']);
    }

    private function requireUsable(stdClass $invite, int $errorCode = 422): void
    {
        $message = match ($this->invitationStatus($invite)) {
            'revoked' => 'Mã mời đã bị thu hồi. Hãy xin mã mới và gửi lại yêu cầu.',
            'expired' => 'Mã mời đã hết hạn. Hãy xin mã mới và gửi lại yêu cầu.',
            'used_up' => 'Mã mời đã hết lượt duyệt. Hãy xin mã mới và gửi lại yêu cầu.',
            default => null,
        };
        abort_if($message !== null, $errorCode, $message ?? '');
    }

    private function invitationStatus(stdClass $invite): string
    {
        return match (true) {
            $invite->revoked_at !== null => 'revoked',
            Carbon::parse($invite->expires_at)->lte(now()) => 'expired',
            (int) $invite->approvals >= (int) $invite->max_approvals => 'used_up',
            default => 'active',
        };
    }

    private function invitationData(stdClass $invite): array
    {
        return [
            'id' => (int) $invite->id, 'suffix' => $invite->code_suffix, 'status' => $this->invitationStatus($invite),
            'approvals' => (int) $invite->approvals, 'max_approvals' => (int) $invite->max_approvals,
            'expires_at' => Carbon::parse($invite->expires_at)->toIso8601String(),
        ];
    }

    private function requests(): Builder
    {
        return DB::table('property_join_requests as j')
            ->join('rental_properties as p', 'p.id', '=', 'j.property_id')
            ->join('users as u', 'u.id', '=', 'j.user_id')
            ->join('property_invitations as i', fn ($join) => $join
                ->on('i.id', '=', 'j.invitation_id')->on('i.property_id', '=', 'j.property_id'))
            ->leftJoin('property_memberships as m', fn ($join) => $join
                ->on('m.property_id', '=', 'j.property_id')->on('m.user_id', '=', 'j.user_id'))
            ->select(['j.*', 'p.name as property_name', 'u.full_name', 'u.username', 'u.contact_phone',
                'i.expires_at', 'i.revoked_at', 'i.approvals', 'i.max_approvals', 'm.status as membership_status', 'm.tenant_id']);
    }

    private function ownRequest(int $userId, int $id): array
    {
        return $this->requestData($this->requests()->where('j.user_id', $userId)->where('j.id', $id)->first());
    }

    private function ownerRequest(int $propertyId, int $id): stdClass
    {
        return $this->requests()->where('j.property_id', $propertyId)->where('j.id', $id)->first();
    }

    private function requestData(stdClass $row): array
    {
        return [
            'id' => (int) $row->id, 'property_id' => (int) $row->property_id, 'property_name' => $row->property_name,
            'status' => $row->status, 'invitation_status' => $this->invitationStatus($row), 'membership_status' => $row->membership_status,
            'created_at' => Carbon::parse($row->created_at)->toIso8601String(),
            'decided_at' => $row->decided_at ? Carbon::parse($row->decided_at)->toIso8601String() : null,
        ];
    }

    private function ownerRequestData(stdClass $row): array
    {
        return $this->requestData($row) + [
            'user_id' => (int) $row->user_id, 'full_name' => $row->full_name, 'username' => $row->username,
            'contact_phone' => $row->contact_phone, 'tenant_id' => $row->tenant_id === null ? null : (int) $row->tenant_id,
        ];
    }

    private function close(stdClass $row, string $status, int $actorId): void
    {
        app(\App\Services\LiveUpdates::class)->user((int) $row->user_id);
        if ($row->status === $status) {
            return;
        }
        abort_unless($row->status === 'pending', 409, 'Yêu cầu này đã được xử lý.');
        DB::table('property_join_requests')->where('id', $row->id)->update([
            'status' => $status, 'pending_key' => null, 'decided_at' => now(), 'decided_by' => $actorId, 'updated_at' => now(),
        ]);
    }

    private function page($rows, callable $map): array
    {
        return ['data' => $rows->getCollection()->map($map)->all(), 'meta' => [
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total(),
        ]];
    }
}
