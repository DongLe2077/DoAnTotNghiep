<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 401, 'Bạn cần đăng nhập.');

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Tài khoản đã bị khóa. Vui lòng liên hệ chủ trọ.'], 401);
        }

        abort_unless($user->hasRole(User::ROLE_OWNER, User::ROLE_TENANT), 403, 'Tài khoản không có quyền truy cập.');

        return $next($request);
    }
}
