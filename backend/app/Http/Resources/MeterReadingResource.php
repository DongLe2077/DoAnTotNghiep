<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeterReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occupancy_service_id' => $this->occupancy_service_id,
            'occupancy_service' => new OccupancyServiceResource($this->whenLoaded('occupancyService')),
            'billing_month' => $this->period_start->format('Y-m'),
            'period_start' => $this->period_start->format('Y-m-d H:i:s'),
            'period_end' => $this->period_end->format('Y-m-d H:i:s'),
            'previous_reading' => $this->previous_reading,
            'current_reading' => $this->current_reading,
            'consumption' => $this->consumption,
            'recorded_by' => $this->recorded_by,
            'recorded_at' => $this->recorded_at->format('Y-m-d H:i:s'),
            'is_billed' => $this->when($this->relationLoaded('invoiceItem'), fn () => $this->invoiceItem !== null),
            'invoice_id' => $this->whenLoaded('invoiceItem', fn () => $this->invoiceItem?->invoice_id),
        ];
    }
}
