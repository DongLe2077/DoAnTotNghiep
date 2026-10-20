<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OccupancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rental_group_id' => $this->rental_group_id,
            'group' => $this->whenLoaded('rentalGroup', fn () => $this->rentalGroup->only(['id', 'group_code'])),
            'room_id' => $this->room_id,
            'room' => $this->whenLoaded('room', fn () => $this->room->only(['id', 'room_number', 'capacity', 'operational_status'])),
            'previous_occupancy_id' => $this->previous_occupancy_id,
            'next_occupancy_id' => $this->whenLoaded('nextOccupancy', fn () => $this->nextOccupancy?->id),
            'started_at' => $this->started_at?->format('Y-m-d H:i:s'),
            'ended_at' => $this->ended_at?->format('Y-m-d H:i:s'),
            'monthly_rent' => $this->monthly_rent,
            'is_active' => $this->ended_at === null,
            'resident_count' => $this->ended_at === null ? (int) $this->resident_count : 0,
            'members' => TenantStayResource::collection($this->whenLoaded('tenantStays')),
            'representatives' => RepresentativeResource::collection($this->whenLoaded('representativeAssignments')),
            'services' => OccupancyServiceResource::collection($this->whenLoaded('occupancyServices')),
        ];
    }
}
