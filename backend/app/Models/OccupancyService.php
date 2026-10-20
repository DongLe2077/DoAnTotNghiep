<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OccupancyService extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'occupancy_services';

    protected $fillable = [
        'occupancy_id',
        'service_id',
        'effective_from',
        'effective_until',
        'unit_price',
        'billing_method',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'occupancy_id' => 'integer',
            'service_id' => 'integer',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'unit_price' => 'decimal:4',
            'active_service_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class, 'occupancy_id', 'id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'occupancy_service_id', 'id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'occupancy_service_id', 'id');
    }
}
