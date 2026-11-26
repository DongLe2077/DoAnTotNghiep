<?php

namespace App\Http\Resources;

use App\Services\AppNotifications;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->type === 'welcome'
            ? app(AppNotifications::class)->welcomeData($request->user('web'), $this->data['name'] ?? null)
            : $this->data;

        return ['id' => $this->id, 'type' => $this->type, 'data' => $data,
            'invoice_id' => $this->invoice_id, 'maintenance_request_id' => $this->maintenance_request_id, 'announcement_id' => $this->announcement_id,
            'is_read' => $this->read_at !== null, 'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s')];
    }
}
