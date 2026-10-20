<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_number' => $this->room_number,
            'capacity' => $this->capacity,
            'default_monthly_rent' => $this->default_monthly_rent,
            'operational_status' => $this->operational_status,
            'resident_count' => (int) $this->resident_count,
            'occupancy_status' => $this->occupancy_status,
            'can_delete' => (int) $this->occupancies_count === 0,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
