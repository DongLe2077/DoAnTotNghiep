<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantStay extends Model
{
    protected $table = 'tenant_stays';

    protected $fillable = [
        'occupancy_id',
        'tenant_id',
        'moved_in_at',
        'moved_out_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'occupancy_id' => 'integer',
            'tenant_id' => 'integer',
            'moved_in_at' => 'datetime',
            'moved_out_at' => 'datetime',
            'active_tenant_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class, 'occupancy_id', 'id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function representativeAssignments(): HasMany
    {
        return $this->hasMany(RepresentativeAssignment::class, 'tenant_stay_id', 'id');
    }
}
