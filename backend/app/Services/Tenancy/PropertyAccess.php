<?php

namespace App\Services\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Explicit per-operation access, independent of the account's legacy global role. */
final class PropertyAccess
{
    public function owner(User $user, int $propertyId): void
    {
        $this->member($user, $propertyId);
        abort_unless(DB::table('rental_properties')->where('id', $propertyId)->where('owner_user_id', $user->id)->exists(), 403, 'Bạn không phải chủ khu trọ này.');
    }

    public function member(User $user, int $propertyId): object
    {
        // Refresh status from the database, including when a previously issued context is reused.
        abort_unless(DB::table('users')->where('id', $user->id)->where('is_active', true)->exists(), 403, 'Tài khoản không còn hoạt động.');
        $member = DB::table('property_memberships')->where('property_id', $propertyId)->where('user_id', $user->id)
            ->where('status', 'approved')->whereNull('revoked_at')->first();
        abort_unless($member, 403, 'Bạn chưa được duyệt hoặc không còn quyền vào khu trọ này.');

        return $member;
    }

    public function tenantId(User $user, int $propertyId): int
    {
        $membership = $this->member($user, $propertyId);
        abort_unless($membership->tenant_id && DB::table('tenants')->where('id', $membership->tenant_id)->where('property_id', $propertyId)->exists(),
            403, 'Tài khoản chưa được liên kết với hồ sơ thuê tại khu trọ này.');

        return (int) $membership->tenant_id;
    }
}
