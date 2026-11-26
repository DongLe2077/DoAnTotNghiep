<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\PrepareApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/web.php', __DIR__.'/../routes/auth.php'],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['api/webhooks/payos', 'api/webhooks/payos/*']);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
        $middleware->prepend(PrepareApiResponse::class);
        $middleware->alias([
            'property.context' => \App\Http\Middleware\SelectProperty::class,
            'active.user' => EnsureActiveUser::class,
            'role' => EnsureRole::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureRole::class);
        $middleware->prependToPriorityList(EnsureRole::class, EnsureActiveUser::class);
        $middleware->prependToPriorityList(EnsureRole::class, \App\Http\Middleware\SelectProperty::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception) => $request->is('api', 'api/*') || $request->expectsJson());
    })->create();
