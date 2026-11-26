<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledDeletion extends Model
{
    use \App\Models\Concerns\BelongsToProperty;

    public $timestamps = false;

    protected $fillable = ['record_type', 'record_id', 'label', 'records', 'purge_at', 'problem', 'photo_paths'];

    protected $casts = [
        'record_id' => 'integer',
        'records' => 'array',
        'purge_at' => 'immutable_datetime',
        'photo_paths' => 'array',
    ];
}
