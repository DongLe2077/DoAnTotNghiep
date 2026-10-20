<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'occupancy_id',
        'item_type',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'occupancy_service_id',
        'meter_reading_id',
        'charge_period_start',
        'charge_period_end',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'invoice_id' => 'integer',
            'occupancy_id' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:2',
            'occupancy_service_id' => 'integer',
            'meter_reading_id' => 'integer',
            'charge_period_start' => 'datetime',
            'charge_period_end' => 'datetime',
            'rent_invoice_id' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class, 'occupancy_id', 'id');
    }

    public function occupancyService(): BelongsTo
    {
        return $this->belongsTo(OccupancyService::class, 'occupancy_service_id', 'id');
    }

    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class, 'meter_reading_id', 'id');
    }
}
