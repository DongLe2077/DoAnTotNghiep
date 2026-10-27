<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRequestHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['maintenance_request_id', 'revision', 'action', 'before_status', 'after_status',
        'before_resolution', 'after_resolution', 'performed_by', 'actor_username', 'actor_role', 'created_at'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'maintenance_request_id' => 'integer', 'revision' => 'integer',
            'performed_by' => 'integer', 'created_at' => 'datetime'];
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
