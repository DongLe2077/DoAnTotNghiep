<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceRequest extends Model
{
    public const CREATED_AT = 'submitted_at';

    protected $table = 'maintenance_requests';

    protected $fillable = [
        'occupancy_id',
        'submitted_by',
        'representative_assignment_id',
        'title',
        'description',
        'status',
        'resolution',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'occupancy_id' => 'integer',
            'submitted_by' => 'integer',
            'representative_assignment_id' => 'integer',
            'submitted_at' => 'datetime',
            'updated_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class, 'occupancy_id', 'id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    public function representativeAssignment(): BelongsTo
    {
        return $this->belongsTo(RepresentativeAssignment::class, 'representative_assignment_id', 'id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MaintenancePhoto::class)->orderBy('id');
    }
}
