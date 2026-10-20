<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'receipt_number' => 'PT-'.$this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice->invoice_number),
            'billing_month' => $this->whenLoaded('invoice', fn () => $this->invoice->billing_month->format('Y-m')),
            'room' => $this->whenLoaded('invoice', fn () => $this->invoice->occupancy->room->only(['id', 'room_number'])),
            'group' => $this->whenLoaded('invoice', fn () => $this->invoice->occupancy->rentalGroup->only(['id', 'group_code'])),
            'amount' => $this->amount, 'paid_at' => $this->paid_at->format('Y-m-d H:i:s'),
            'payment_method' => $this->payment_method, 'idempotency_key' => $this->idempotency_key,
            'recorded_by' => $this->recorded_by, 'recorded_at' => $this->recorded_at->format('Y-m-d H:i:s'),
        ];
    }
}
