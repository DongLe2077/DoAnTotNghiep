<?php

namespace App\Services;

use App\Models\RepresentativeAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantRegistry
{
    public function query(): Builder
    {
        return Tenant::query()->withCount('stays')->with([
            'user',
            'stays' => fn ($query) => $query->whereNull('moved_out_at')
                ->whereHas('occupancy', fn ($query) => $query->whereNull('ended_at'))
                ->with('occupancy.room', 'occupancy.rentalGroup'),
        ]);
    }

    public function create(array $attributes): Tenant
    {
        try {
            return Tenant::create($attributes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['identity_number' => 'Số giấy tờ tùy thân đã được dùng cho người khác.']);
        }
    }

    public function update(Tenant $tenant, array $attributes): Tenant
    {
        try {
            return DB::transaction(function () use ($tenant, $attributes) {
                $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
                $locked->update($attributes);

                return $locked;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['identity_number' => 'Số giấy tờ tùy thân đã được dùng cho người khác.']);
        }
    }

    public function delete(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            abort_if($locked->stays()->exists() || $locked->user()->exists(), 409, 'Hồ sơ đã có lịch sử cư trú hoặc tài khoản nên không thể xóa.');
            $locked->delete();
        }, 3);
    }

    public function saveAccount(Tenant $tenant, array $attributes): User
    {
        try {
            return DB::transaction(function () use ($tenant, $attributes) {
                $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
                $user = User::query()->where('tenant_id', $locked->id)->lockForUpdate()->first();
                abort_if($user !== null && ! $user->hasRole(User::ROLE_TENANT), 409, 'Không thể thay đổi tài khoản chủ trọ qua hồ sơ người thuê.');
                if ($user === null && (! isset($attributes['username']) || ! isset($attributes['password']))) {
                    throw ValidationException::withMessages(['username' => 'Tài khoản mới cần tên đăng nhập và mật khẩu.']);
                }
                if (array_key_exists('is_active', $attributes) && ! (bool) $attributes['is_active']) {
                    $isRepresentative = RepresentativeAssignment::query()->whereNull('ended_at')
                        ->whereHas('tenantStay', fn ($query) => $query->where('tenant_id', $locked->id))->exists();
                    abort_if($isRepresentative, 409, 'Hãy đổi người đại diện trước khi khóa tài khoản đang đại diện cho đợt thuê.');
                }
                $user ??= new User;
                $user->tenant_id = $locked->id;
                $user->role = User::ROLE_TENANT;
                $user->is_active = $attributes['is_active'] ?? $user->is_active ?? true;
                if (isset($attributes['username'])) {
                    $user->username = $attributes['username'];
                }
                if (isset($attributes['password'])) {
                    $user->password_hash = Hash::make($attributes['password']);
                }
                $user->save();

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'Tên đăng nhập đã tồn tại.']);
        }
    }
}
