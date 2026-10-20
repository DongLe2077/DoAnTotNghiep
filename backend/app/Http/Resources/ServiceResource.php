<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_code' => $this->service_code,
            'name' => $this->name,
            'unit' => $this->unit,
            'billing_method' => $this->billing_method,
            'default_unit_price' => $this->default_unit_price,
            'is_active' => $this->is_active,
            'can_delete' => (int) $this->occupancy_services_count === 0,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
