<?php

namespace App\Models\Concerns;

use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyQueries;

trait BelongsToProperty
{
    public static function bootBelongsToProperty(): void
    {
        static::addGlobalScope('property', function ($query) {
            $context = app(PropertyContext::class);
            if (! $context->enabled()) {
                return;
            }
            $table = $query->getModel()->getTable();
            $id = $context->requireId();
            if (in_array($table, PropertyQueries::ROOTS, true)) {
                $query->where($table.'.property_id', $id);
            } elseif (isset(PropertyQueries::PARENTS[$table])) {
                foreach (PropertyQueries::PARENTS[$table] as $column => $parent) {
                    $query->whereIn($table.'.'.$column, app(PropertyQueries::class)->scoped($id, $parent)->select($parent.'.id'));
                }
            } elseif ($table === 'app_notifications') {
                $query->where(function ($q) use ($id, $table) {
                    foreach (['invoice_id' => 'invoices', 'maintenance_request_id' => 'maintenance_requests', 'announcement_id' => 'announcements'] as $column => $parent) {
                        $q->orWhereIn($table.'.'.$column, app(PropertyQueries::class)->scoped($id, $parent)->select($parent.'.id'));
                    }
                });
            } else {
                throw new \LogicException('Unreviewed model scope: '.$table);
            }
        });
        static::saving(function ($model) {
            $context = app(PropertyContext::class);
            if (! $context->enabled()) {
                return;
            }
            $id = $context->requireId();
            $table = $model->getTable();
            $queries = app(PropertyQueries::class);
            if ($model->exists) {
                abort_unless($queries->scoped($id, $table)->where('id', $model->getKey())->exists(), 404);
            }
            if (in_array($table, PropertyQueries::ROOTS, true)) {
                abort_if($model->property_id !== null && (int) $model->property_id !== $id, 422, 'Dữ liệu không thuộc khu trọ đang chọn.');
                $model->property_id = $id;
            }
            foreach (PropertyQueries::PARENTS[$table] ?? [] as $key => $parent) {
                abort_unless($model->$key && $queries->scoped($id, $parent)->where('id', $model->$key)->exists(), 422, 'Dữ liệu liên kết không thuộc khu trọ đang chọn.');
            }
        });
        static::deleting(function ($model) {
            $context = app(PropertyContext::class);
            if ($context->enabled()) {
                abort_unless(app(PropertyQueries::class)->scoped($context->requireId(), $model->getTable())->where('id', $model->getKey())->exists(), 404);
            }
        });
    }
}
