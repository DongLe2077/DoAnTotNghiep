<?php

namespace App\Services\Tenancy;

use Illuminate\Validation\DatabasePresenceVerifier;

class PropertyPresenceVerifier extends DatabasePresenceVerifier
{
    protected function table($table)
    {
        $context = app(PropertyContext::class);
        if ($context->enabled() && (in_array($table, PropertyQueries::ROOTS, true) || isset(PropertyQueries::PARENTS[$table]))) {
            return app(PropertyQueries::class)->scoped($context->requireId(), $table)->useWritePdo();
        }

        return parent::table($table);
    }
}
