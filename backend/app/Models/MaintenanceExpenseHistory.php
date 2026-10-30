<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceExpenseHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['maintenance_expense_link_id', 'maintenance_request_id', 'cash_transaction_id', 'revision',
        'action', 'before_data', 'after_data', 'performed_by', 'actor_username', 'actor_role', 'reason',
        'idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'maintenance_expense_link_id' => 'integer', 'maintenance_request_id' => 'integer',
            'cash_transaction_id' => 'integer', 'revision' => 'integer', 'performed_by' => 'integer',
            'before_data' => 'array', 'after_data' => 'array', 'created_at' => 'datetime'];
    }

    public function maintenanceExpenseLink(): BelongsTo
    {
        return $this->belongsTo(MaintenanceExpenseLink::class);
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class)->withTrashed();
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
