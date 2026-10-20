<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepresentativeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occupancy_id' => $this->occupancy_id,
            'tenant_stay_id' => $this->tenant_stay_id,
            'tenant' => $this->whenLoaded('tenantStay', fn () => $this->tenantStay->tenant->only(['id', 'full_name', 'phone_number'])),
            'started_at' => $this->started_at?->format('Y-m-d H:i:s'),
            'ended_at' => $this->ended_at?->format('Y-m-d H:i:s'),
            'is_active' => $this->ended_at === null,
        ];
    }
}
