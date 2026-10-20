<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Occupancy extends Model
{
    protected $table = 'occupancies';

    protected $fillable = [
        'rental_group_id',
        'previous_occupancy_id',
        'room_id',
        'started_at',
        'ended_at',
        'monthly_rent',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'rental_group_id' => 'integer',
            'previous_occupancy_id' => 'integer',
            'room_id' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'monthly_rent' => 'decimal:2',
            'active_room_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'active_rental_group_id' => 'integer',
        ];
    }

    public function rentalGroup(): BelongsTo
    {
        return $this->belongsTo(RentalGroup::class, 'rental_group_id', 'id');
    }

    public function previousOccupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class, 'previous_occupancy_id', 'id');
    }

    public function nextOccupancy(): HasOne
    {
        return $this->hasOne(Occupancy::class, 'previous_occupancy_id', 'id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id', 'id');
    }

    public function occupancyServices(): HasMany
    {
        return $this->hasMany(OccupancyService::class, 'occupancy_id', 'id');
    }

    public function tenantStays(): HasMany
    {
        return $this->hasMany(TenantStay::class, 'occupancy_id', 'id');
    }

    public function representativeAssignments(): HasMany
    {
        return $this->hasMany(RepresentativeAssignment::class, 'occupancy_id', 'id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'occupancy_id', 'id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'occupancy_id', 'id');
    }

    public function invoiceAccessGrants(): HasMany
    {
        return $this->hasMany(InvoiceAccessGrant::class, 'occupancy_id', 'id');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'occupancy_id', 'id');
    }
}
