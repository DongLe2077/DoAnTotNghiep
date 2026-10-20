<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAccessGrant extends Model
{
    public const CREATED_AT = 'granted_at';

    public const UPDATED_AT = null;

    protected $table = 'invoice_access_grants';

    protected $fillable = [
        'invoice_id',
        'occupancy_id',
        'user_id',
        'representative_assignment_id',
        'granted_by',
        'revoked_at',
        'grant_reason',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'invoice_id' => 'integer',
            'occupancy_id' => 'integer',
            'user_id' => 'integer',
            'representative_assignment_id' => 'integer',
            'granted_by' => 'integer',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'active_invoice_id' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class, 'occupancy_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function representativeAssignment(): BelongsTo
    {
        return $this->belongsTo(RepresentativeAssignment::class, 'representative_assignment_id', 'id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by', 'id');
    }
}
