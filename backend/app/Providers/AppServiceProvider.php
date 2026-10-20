<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('password-change', fn (Request $request) => Limit::perMinute(5)
            ->by((string) $request->user('web')?->id)
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'Bạn đổi mật khẩu quá nhiều lần. Vui lòng thử lại sau ít phút.',
            ], 429, $headers)));

        RateLimiter::for('auth-login-ip', fn (Request $request) => Limit::perMinute(30)
            ->by($request->ip())
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'Bạn đăng nhập quá nhiều lần. Vui lòng thử lại sau ít phút.',
            ], 429, $headers)));
    }
}
