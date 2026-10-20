<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashTransactionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'cash_transaction_id' => $this->cash_transaction_id,
            'revision' => $this->revision, 'action' => $this->action,
            'before' => $this->before_data, 'after' => $this->after_data,
            'performed_by' => $this->performed_by,
            'actor' => $this->whenLoaded('performedBy', fn () => $this->performedBy->only(['id', 'username'])),
            'reason' => $this->reason, 'reverted_history_id' => $this->reverted_history_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s')];
    }
}
