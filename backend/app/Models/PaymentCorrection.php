<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['invoice_id', 'payment_id', 'replacement_payment_id', 'action', 'reason', 'before_data', 'after_data', 'performed_by', 'actor_username', 'idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'invoice_id' => 'integer', 'payment_id' => 'integer', 'replacement_payment_id' => 'integer',
            'performed_by' => 'integer', 'before_data' => 'array', 'after_data' => 'array', 'created_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function replacementPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'replacement_payment_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
