<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['invoice_id', 'revision', 'action', 'amount', 'reason', 'before_data', 'after_data',
        'performed_by', 'idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['invoice_id' => 'integer', 'revision' => 'integer', 'performed_by' => 'integer',
            'amount' => 'decimal:2', 'before_data' => 'array', 'after_data' => 'array', 'created_at' => 'datetime'];
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
