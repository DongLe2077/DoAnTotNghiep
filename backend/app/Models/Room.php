<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $table = 'rooms';

    protected $fillable = [
        'room_number',
        'capacity',
        'default_monthly_rent',
        'operational_status',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'capacity' => 'integer',
            'default_monthly_rent' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(Occupancy::class, 'room_id', 'id');
    }
}
