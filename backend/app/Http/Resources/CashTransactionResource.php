<?php

namespace App\Http\Resources;

use App\Services\CashBook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'transaction_type' => $this->transaction_type,
            'amount' => $this->amount, 'occurred_at' => $this->occurred_at->format('Y-m-d H:i:s'),
            'description' => $this->description, 'recorded_by' => $this->recorded_by,
            'recorded_at' => $this->recorded_at->format('Y-m-d H:i:s'),
            'revision' => $this->revision, 'is_deleted' => $this->trashed(),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'version_token' => CashBook::version($this->resource),
        ];
    }
}
