<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantStayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occupancy_id' => $this->occupancy_id,
            'tenant_id' => $this->tenant_id,
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant->only(['id', 'full_name', 'phone_number'])),
            'room' => $this->when($this->relationLoaded('occupancy') && $this->occupancy->relationLoaded('room'), fn () => $this->occupancy->room->only(['id', 'room_number'])),
            'moved_in_at' => $this->moved_in_at?->format('Y-m-d H:i:s'),
            'moved_out_at' => $this->moved_out_at?->format('Y-m-d H:i:s'),
            'is_active' => $this->moved_out_at === null,
        ];
    }
}
