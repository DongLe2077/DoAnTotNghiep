<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransactionHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['cash_transaction_id', 'revision', 'action', 'before_data', 'after_data', 'performed_by', 'reason', 'reverted_history_id'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'cash_transaction_id' => 'integer', 'revision' => 'integer', 'performed_by' => 'integer',
            'reverted_history_id' => 'integer', 'before_data' => 'array', 'after_data' => 'array', 'created_at' => 'datetime'];
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class)->withTrashed();
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function revertedHistory(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverted_history_id');
    }
}
