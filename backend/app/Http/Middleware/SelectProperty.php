<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\PropertyAccess;
use App\Services\Tenancy\PropertyContext;
use Closure;
use Illuminate\Http\Request;

class SelectProperty
{
    public function handle(Request $request, Closure $next, ?string $mode = null)
    {
        $context = app(PropertyContext::class);
        if (! $context->enabled() || ($mode === 'optional' && ! $request->hasHeader('X-Property-ID'))) {
            return $next($request);
        }
        $raw = $request->header('X-Property-ID');
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_unless($id, 409, 'Hãy chọn khu trọ trước khi tiếp tục.');
        app(PropertyAccess::class)->member($request->user('web'), $id);

        return $context->run($id, fn () => $next($request));
    }
}
