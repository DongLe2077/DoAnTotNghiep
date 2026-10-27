<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['title', 'body', 'audience', 'room_ids', 'room_snapshot', 'created_by', 'idempotency_key', 'request_fingerprint'];

    protected $hidden = ['idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'created_by' => 'integer', 'room_ids' => 'array', 'room_snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class)->where('type', 'owner_announcement');
    }
}
