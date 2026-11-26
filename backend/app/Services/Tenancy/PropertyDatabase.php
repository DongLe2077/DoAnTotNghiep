<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\DB;

/** Explicit replacement for raw domain reads; authentication/ownership metadata uses DB directly. */
final class PropertyDatabase
{
    public static function table(string $table)
    {
        $context = app(PropertyContext::class);
        if (! $context->enabled()) {
            return DB::table($table);
        }
        $parts = preg_split('/\s+as\s+/i', $table);
        $name = $parts[0];
        $query = app(PropertyQueries::class)->scoped($context->requireId(), $name);
        if (count($parts) === 1) {
            return $query;
        }

        // Put the scope inside a derived table so existing aliases remain valid.
        return DB::query()->fromSub($query, $parts[1]);
    }
}
