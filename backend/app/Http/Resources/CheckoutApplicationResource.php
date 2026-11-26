<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $endedAt = $this->occupancy->ended_at;

        return [
            'id' => $this->id,
            'occupancy_id' => (int) $this->occupancy_id,
            'room_number' => $this->occupancy->room->room_number,
            'requester_name' => $this->requesterStay->tenant->full_name,
            'status' => $this->status === 'pending' && $endedAt !== null ? 'closed' : $this->status,
            'requested_at' => $this->created_at->format('Y-m-d H:i:s'),
            'ended_at' => $endedAt?->format('Y-m-d H:i:s'),
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
