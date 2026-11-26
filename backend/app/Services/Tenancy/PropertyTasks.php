<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\DB;

final class PropertyTasks
{
    public function each(callable $task): array
    {
        $context = app(PropertyContext::class);
        if (! $context->enabled()) {
            return [$task()];
        }
        $results = [];
        foreach (DB::table('rental_properties')->whereNotNull('owner_user_id')->orderBy('id')->pluck('id') as $id) {
            $results[$id] = $context->run((int) $id, $task);
        }

        return $results;
    }
}
