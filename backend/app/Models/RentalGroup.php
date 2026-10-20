<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalGroup extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'rental_groups';

    protected $fillable = [
        'group_code',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(Occupancy::class, 'rental_group_id', 'id');
    }
}
