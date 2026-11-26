<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyMembership extends Model
{
    protected $guarded = ['id', 'property_id', 'user_id', 'role', 'status', 'tenant_id', 'approved_by', 'approved_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['property_id' => 'integer', 'user_id' => 'integer', 'tenant_id' => 'integer',
            'approved_by' => 'integer', 'approved_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function property(): BelongsTo { return $this->belongsTo(RentalProperty::class, 'property_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
