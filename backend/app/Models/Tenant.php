<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use \App\Models\Concerns\BelongsToProperty;

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

    public function user(): \Illuminate\Database\Eloquent\Relations\Relation
    {
        if (app(\App\Services\Tenancy\PropertyContext::class)->enabled()) {
            return $this->hasOneThrough(User::class, PropertyMembership::class, 'tenant_id', 'id', 'id', 'user_id')
                ->where('property_memberships.property_id', app(\App\Services\Tenancy\PropertyContext::class)->requireId())
                ->where('property_memberships.status', 'approved')->whereNull('property_memberships.revoked_at');
        }
        return $this->hasOne(User::class, 'tenant_id', 'id');
    }

    public function stays(): HasMany
    {
        return $this->hasMany(TenantStay::class, 'tenant_id', 'id');
    }
}
