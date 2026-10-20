<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashTransaction extends Model
{
    use SoftDeletes;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $table = 'cash_transactions';

    protected $fillable = [
        'transaction_type',
        'amount',
        'occurred_at',
        'description',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'recorded_by' => 'integer',
            'recorded_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CashTransactionHistory::class);
    }
}
