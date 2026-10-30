<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $table = 'payments';

    protected $fillable = [
        'invoice_id',
        'amount',
        'paid_at',
        'payment_method',
        'idempotency_key',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'invoice_id' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'recorded_by' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }

    public function correction(): HasOne
    {
        return $this->hasOne(PaymentCorrection::class);
    }

    public function replacementFor(): HasOne
    {
        return $this->hasOne(PaymentCorrection::class, 'replacement_payment_id');
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->whereDoesntHave('correction');
    }
}
