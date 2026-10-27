<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['notification_id' => $this->id, 'user_id' => $this->user_id, 'username' => $this->user->username,
            'full_name' => $this->user->tenant?->full_name ?? $this->user->username,
            'room_numbers' => $this->data['room_numbers'] ?? [], 'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'), 'is_active' => $this->user->is_active];
    }
}
