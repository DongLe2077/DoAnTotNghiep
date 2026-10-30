<?php

namespace App\Http\Resources;

use App\Services\CashBook;
use App\Services\MaintenanceExpenses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cash = $this->cashTransaction;

        return ['id' => $this->id, 'maintenance_request_id' => $this->maintenance_request_id,
            'cash_transaction_id' => $this->cash_transaction_id, 'is_active' => $this->is_active,
            'revision' => $this->revision, 'version_token' => MaintenanceExpenses::version($this->resource),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'), 'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'cash_transaction' => ['id' => $cash->id, 'transaction_type' => $cash->transaction_type, 'amount' => $cash->amount,
                'description' => $cash->description, 'occurred_at' => $cash->occurred_at->format('Y-m-d H:i:s'),
                'is_deleted' => $cash->trashed(), 'version_token' => CashBook::version($cash)],
            'maintenance_request' => ['id' => $this->maintenanceRequest->id, 'title' => $this->maintenanceRequest->title],
            'included_in_total' => $this->is_active && ! $cash->trashed() && $cash->transaction_type === 'expense'];
    }
}
