<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeterReading extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $table = 'meter_readings';

    protected $fillable = [
        'occupancy_service_id',
        'period_start',
        'period_end',
        'previous_reading',
        'current_reading',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'occupancy_service_id' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'previous_reading' => 'decimal:3',
            'current_reading' => 'decimal:3',
            'consumption' => 'decimal:3',
            'recorded_by' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function occupancyService(): BelongsTo
    {
        return $this->belongsTo(OccupancyService::class, 'occupancy_service_id', 'id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }

    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class, 'meter_reading_id', 'id');
    }
}
