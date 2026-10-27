<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'invoice_id', 'maintenance_request_id', 'announcement_id', 'dedupe_key', 'data', 'read_at'];

    protected $hidden = ['dedupe_key'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'user_id' => 'integer', 'invoice_id' => 'integer', 'maintenance_request_id' => 'integer',
            'announcement_id' => 'integer', 'data' => 'array', 'read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
