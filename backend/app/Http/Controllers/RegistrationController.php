<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterAccountRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\AppNotifications;
use App\Services\Tenancy\PropertyContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function options(PropertyContext $context): JsonResponse
    {
        return response()->json(['data' => ['enabled' => $context->enabled()]]);
    }

    public function store(RegisterAccountRequest $request, AppNotifications $notifications): JsonResponse
    {
        $fields = $request->validated();
        try {
            $user = DB::transaction(function () use ($fields, $notifications) {
                $user = new User;
                $user->full_name = $fields['full_name'];
                $user->contact_phone = $fields['contact_phone'];
                $user->username = $fields['username'];
                $user->password_hash = Hash::make($fields['password']);
                $user->role = User::ROLE_TENANT;
                $user->is_active = true;
                $user->save();
                $notifications->welcome($user);

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'Tên đăng nhập này đã có người dùng.']);
        }

        Auth::guard('web')->login($user);
        $request->session()->forget('property_id');
        $request->session()->regenerate();

        return response()->json(['message' => 'Đã tạo tài khoản.', 'data' => new AuthUserResource($user)], 201);
    }
}
