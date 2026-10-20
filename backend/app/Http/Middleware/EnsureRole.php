<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 401, 'Bạn cần đăng nhập.');
        abort_unless($user->is_active && $user->hasRole(...$roles), 403, 'Bạn không có quyền truy cập chức năng này.');

        return $next($request);
    }
}
