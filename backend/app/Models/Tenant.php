<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $table = 'tenants';

    protected $fillable = [
        'full_name',
        'phone_number',
        'identity_number',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'tenant_id', 'id');
    }

    public function stays(): HasMany
    {
        return $this->hasMany(TenantStay::class, 'tenant_id', 'id');
    }
}
