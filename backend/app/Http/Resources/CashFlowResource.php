<?php

namespace App\Http\Resources;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashFlowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $amount = BigDecimal::of((string) $this->amount)->toScale(2, RoundingMode::HALF_UP);

        return [
            'id' => $this->source.':'.$this->source_id, 'source' => $this->source, 'source_id' => (int) $this->source_id,
            'entry_type' => $this->entry_type, 'direction' => $this->entry_type === 'expense' ? 'expense' : 'income',
            'amount' => (string) $amount, 'signed_amount' => (string) ($this->entry_type === 'expense' ? $amount->negated() : $amount),
            'occurred_at' => $this->occurred_at,
            'description' => $this->source === 'payment' ? 'Thu hóa đơn '.$this->invoice_number : $this->description,
            'invoice_id' => $this->invoice_id === null ? null : (int) $this->invoice_id,
            'invoice_number' => $this->invoice_number, 'payment_method' => $this->payment_method,
            'recorded_by' => (int) $this->recorded_by, 'recorded_at' => $this->recorded_at,
            'can_edit' => $this->source === 'cash_transaction', 'can_delete' => $this->source === 'cash_transaction',
        ];
    }
}
