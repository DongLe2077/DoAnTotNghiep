<?php

namespace App\Http\Resources;

use App\Services\InvoiceReports;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivableGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group_by' => $request->input('group_by', 'room'),
            'group_id' => (int) $this->group_id,
            'group_label' => $this->group_label,
            'invoice_count' => (int) $this->invoice_count,
            'total_amount' => InvoiceReports::money($this->total_amount),
            'paid_amount' => InvoiceReports::money($this->paid_amount),
            'outstanding_amount' => InvoiceReports::money($this->outstanding_amount),
        ];
    }
}
