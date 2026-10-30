<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceExpenseHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'maintenance_request_id' => $this->maintenance_request_id,
            'cash_transaction_id' => $this->cash_transaction_id, 'revision' => $this->revision, 'action' => $this->action,
            'before' => $this->before_data, 'after' => $this->after_data, 'reason' => $this->reason,
            'actor' => ['id' => $this->performed_by, 'username' => $this->actor_username, 'role' => $this->actor_role],
            'created_at' => $this->created_at->format('Y-m-d H:i:s')];
    }
}
