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
        $this->app->singleton(\App\Services\LiveUpdates::class);
        $this->app->singleton(\App\Services\Tenancy\PropertyContext::class);
        $this->app->extend('validation.presence', fn ($verifier, $app) => new \App\Services\Tenancy\PropertyPresenceVerifier($app['db']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $live = $this->app->make(\App\Services\LiveUpdates::class);
        \Illuminate\Support\Facades\DB::listen($live->observe(...));
        $this->app->terminating($live->flush(...));

        RateLimiter::for('account-registration', fn (Request $request) => [
            Limit::perMinute(6)->by('minute:'.$request->ip()),
            Limit::perHour(25)->by('hour:'.$request->ip()),
        ]);
        RateLimiter::for('property-invitation', fn (Request $request) => [
            Limit::perMinute(12)->by('user:'.$request->user('web')?->id),
            Limit::perMinute(45)->by('ip:'.$request->ip()),
        ]);

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
