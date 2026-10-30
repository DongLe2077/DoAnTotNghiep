<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceExpenseLink extends Model
{
    protected $fillable = ['cash_transaction_id', 'maintenance_request_id', 'is_active', 'revision'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'cash_transaction_id' => 'integer', 'maintenance_request_id' => 'integer',
            'is_active' => 'boolean', 'revision' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class)->withTrashed();
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MaintenanceExpenseHistory::class);
    }
}
