<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Services\AccountPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function csrfCookie(): Response
    {
        return response()->noContent();
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (Auth::guard('web')->check()) {
            return response()->json(['message' => 'Bạn đã đăng nhập. Vui lòng đăng xuất trước khi đổi tài khoản.'], 409);
        }

        $request->authenticate();
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Đăng nhập thành công.',
            'data' => new AuthUserResource($request->user('web')),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => new AuthUserResource($request->user('web'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }

    public function changePassword(ChangePasswordRequest $request, AccountPassword $passwords): JsonResponse
    {
        $passwords->change($request->user('web'), $request->validated());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Đã đổi mật khẩu. Vui lòng đăng nhập lại.', 'requires_login' => true]);
    }
}
