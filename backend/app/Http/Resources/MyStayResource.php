<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyStayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $occupancy = $this->occupancy;
        $active = $this->moved_out_at === null && $occupancy->ended_at === null
            && $this->moved_in_at->lte(now()) && $occupancy->started_at->lte(now());
        $representative = $active && (bool) $this->is_current_representative;

        return [
            'id' => $this->id,
            'occupancy_id' => $this->occupancy_id,
            'moved_in_at' => $this->moved_in_at->format('Y-m-d H:i:s'),
            'moved_out_at' => $this->moved_out_at?->format('Y-m-d H:i:s'),
            'is_active' => $active,
            'is_current_representative' => $representative,
            'can_submit_maintenance' => $representative,
            'room' => $occupancy->room->only(['id', 'room_number', 'capacity', 'operational_status']),
            'group' => $occupancy->rentalGroup->only(['id', 'group_code']),
            'occupancy_started_at' => $occupancy->started_at->format('Y-m-d H:i:s'),
            'occupancy_ended_at' => $occupancy->ended_at?->format('Y-m-d H:i:s'),
            'monthly_room_rent' => $occupancy->monthly_rent,
            'services' => $this->when($active && $occupancy->relationLoaded('occupancyServices'),
                fn () => OccupancyServiceResource::collection($occupancy->occupancyServices)),
        ];
    }
}
