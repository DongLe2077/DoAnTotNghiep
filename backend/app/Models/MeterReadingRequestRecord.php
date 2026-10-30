<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReadingRequestRecord extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'meter_reading_requests';

    protected $fillable = ['idempotency_key', 'occupancy_id', 'occupancy_service_id', 'meter_reading_id', 'original_reading_id', 'recorded_by', 'request_data', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'occupancy_id' => 'integer', 'occupancy_service_id' => 'integer', 'meter_reading_id' => 'integer',
            'original_reading_id' => 'integer', 'recorded_by' => 'integer', 'request_data' => 'array', 'created_at' => 'datetime'];
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class);
    }

    public function occupancyService(): BelongsTo
    {
        return $this->belongsTo(OccupancyService::class);
    }

    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
