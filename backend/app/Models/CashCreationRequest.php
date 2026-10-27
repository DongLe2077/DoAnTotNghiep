<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCreationRequest extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['idempotency_key', 'cash_transaction_id', 'recorded_by', 'request_data', 'request_fingerprint'];

    protected $hidden = ['idempotency_key', 'request_data', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'cash_transaction_id' => 'integer', 'recorded_by' => 'integer',
            'request_data' => 'array', 'created_at' => 'datetime'];
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class)->withTrashed();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
