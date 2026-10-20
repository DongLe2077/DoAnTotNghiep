<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const CREATED_AT = 'issued_at';

    public const UPDATED_AT = null;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'occupancy_id',
        'billing_month',
        'representative_assignment_id',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'occupancy_id' => 'integer',
            'billing_month' => 'date',
            'representative_assignment_id' => 'integer',
            'issued_by' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class, 'occupancy_id', 'id');
    }

    public function representativeAssignment(): BelongsTo
    {
        return $this->belongsTo(RepresentativeAssignment::class, 'representative_assignment_id', 'id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id', 'id');
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(InvoiceAccessGrant::class, 'invoice_id', 'id');
    }
}
