<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'data' => $this->data,
            'invoice_id' => $this->invoice_id, 'maintenance_request_id' => $this->maintenance_request_id,
            'is_read' => $this->read_at !== null, 'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s')];
    }
}
