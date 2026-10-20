<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'service_code',
        'name',
        'unit',
        'billing_method',
        'default_unit_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'default_unit_price' => 'decimal:4',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function occupancyServices(): HasMany
    {
        return $this->hasMany(OccupancyService::class, 'service_id', 'id');
    }
}
