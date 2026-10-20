<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePhoto extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['maintenance_request_id', 'uploaded_by', 'path', 'sha256', 'mime_type', 'byte_size'];

    protected $hidden = ['path', 'sha256'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'maintenance_request_id' => 'integer', 'uploaded_by' => 'integer', 'byte_size' => 'integer', 'created_at' => 'datetime'];
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
