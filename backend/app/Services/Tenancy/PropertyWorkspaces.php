<?php

namespace App\Services\Tenancy;

use App\Models\RentalProperty;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PropertyWorkspaces
{
    public function __construct(private PropertyAccess $access) {}

    public function create(User $actor, array $data, ?string $photoPath = null): RentalProperty
    {
        return DB::transaction(function () use ($actor, $data, $photoPath) {
            abort_unless(User::query()->whereKey($actor->id)->lockForUpdate()->value('is_active'), 403);
            $fields = validator($data, ['name' => 'required|string|max:150', 'address' => 'required|string|max:500',
                'contact_name' => 'nullable|string|max:150', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:254',
                'owner_user_id' => 'prohibited', 'role' => 'prohibited', 'id' => 'prohibited'])->validate();
            if (trim($fields['name']) === '' || trim($fields['address']) === '') {
                throw ValidationException::withMessages(['name' => 'Tên và địa chỉ khu trọ không được để trống.']);
            }
            $property = new RentalProperty;
            $property->name = trim($fields['name']);
            $property->address = trim($fields['address']);
            $property->updated_by = $actor->id;
            $property->owner_user_id = $actor->id;
            $property->fill(array_intersect_key($fields, array_flip(['contact_name', 'phone', 'email'])));
            if ($photoPath !== null) {
                $property->photo_path = $photoPath;
            }
            $property->save();
            DB::table('property_memberships')->insert(['property_id' => $property->id, 'user_id' => $actor->id,
                'role' => 'owner', 'status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(),
                'created_at' => now(), 'updated_at' => now()]);

            return $property;
        });
    }

    public function visibleTo(User $actor): array
    {
        abort_unless(User::query()->whereKey($actor->id)->value('is_active'), 403);

        return DB::table('property_memberships as memberships')
            ->join('rental_properties as properties', 'properties.id', '=', 'memberships.property_id')
            ->where('memberships.user_id', $actor->id)->where('memberships.status', 'approved')->whereNull('memberships.revoked_at')
            ->orderBy('properties.id')->get(['properties.id', 'properties.name', 'properties.address', 'properties.owner_user_id'])->map(fn ($p) => [
                'id' => (int) $p->id, 'name' => $p->name, 'address' => $p->address,
                'role' => (int) $p->owner_user_id === $actor->id ? 'owner' : 'member',
            ])->all();
    }

    /** Binding is an owner action; a phone number alone is never proof of ownership. */
    public function linkTenant(User $actor, int $propertyId, int $memberId, int $tenantId): void
    {
        DB::transaction(function () use ($actor, $propertyId, $memberId, $tenantId) {
            DB::table('rental_properties')->where('id', $propertyId)->lockForUpdate()->first();
            $this->access->owner($actor, $propertyId);
            abort_if($actor->id === $memberId, 409, 'Hãy chọn tài khoản người thuê đã được duyệt trong khu này.');
            abort_unless(User::query()->whereKey($memberId)->where('is_active', true)->exists(), 403);
            $member = DB::table('property_memberships')->where('property_id', $propertyId)->where('user_id', $memberId)->lockForUpdate()->first();
            abort_unless($member && $member->status === 'approved' && ! $member->revoked_at, 403);
            $tenant = DB::table('tenants')->where('id', $tenantId)->where('property_id', $propertyId)->lockForUpdate()->first();
            abort_unless($tenant, 404, 'Hồ sơ không thuộc khu trọ này.');
            abort_if($member->tenant_id !== null && (int) $member->tenant_id !== $tenantId, 409, 'Đã liên kết hồ sơ khác.');
            abort_if(DB::table('property_memberships')->where('tenant_id', $tenantId)->where('id', '!=', $member->id)->exists(), 409, 'Hồ sơ đã có tài khoản liên kết.');
            DB::table('property_memberships')->where('id', $member->id)->update(['tenant_id' => $tenantId, 'updated_at' => now()]);
        });
    }
}
