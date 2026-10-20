<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OccupancyServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occupancy_id' => $this->occupancy_id,
            'service_id' => $this->service_id,
            'service' => $this->whenLoaded('service', fn () => $this->service->only(['id', 'service_code', 'name'])),
            'effective_from' => $this->effective_from?->format('Y-m-d H:i:s'),
            'effective_until' => $this->effective_until?->format('Y-m-d H:i:s'),
            'unit_price' => $this->unit_price,
            'unit' => $this->unit,
            'billing_method' => $this->billing_method,
            'is_active' => $this->effective_until === null,
        ];
    }
}
