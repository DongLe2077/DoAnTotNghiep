<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountPassword
{
    public function change(User $actor, array $data): void
    {
        DB::transaction(function () use ($actor, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless($user->is_active && $user->hasRole(User::ROLE_OWNER, User::ROLE_TENANT), 401);
            if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
                throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
            }
            if (Hash::check($data['password'], $user->getAuthPassword())) {
                throw ValidationException::withMessages(['password' => 'Mật khẩu mới phải khác mật khẩu hiện tại.']);
            }
            $user->password_hash = Hash::make($data['password']);
            $user->save();
        }, 3);
    }
}
