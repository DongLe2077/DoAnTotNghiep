<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepresentativeAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'representative_assignments';

    protected $fillable = [
        'occupancy_id',
        'tenant_stay_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'occupancy_id' => 'integer',
            'tenant_stay_id' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'active_occupancy_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class, 'occupancy_id', 'id');
    }

    public function tenantStay(): BelongsTo
    {
        return $this->belongsTo(TenantStay::class, 'tenant_stay_id', 'id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'representative_assignment_id', 'id');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'representative_assignment_id', 'id');
    }

    public function invoiceAccessGrants(): HasMany
    {
        return $this->hasMany(InvoiceAccessGrant::class, 'representative_assignment_id', 'id');
    }
}
