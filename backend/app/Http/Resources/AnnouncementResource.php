<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'body' => $this->body, 'audience' => $this->audience,
            'rooms' => $this->room_snapshot, 'created_by' => $this->createdBy?->username,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'), 'recipient_count' => (int) $this->recipient_count,
            'read_count' => (int) $this->read_count, 'unread_count' => (int) $this->recipient_count - (int) $this->read_count];
    }
}
