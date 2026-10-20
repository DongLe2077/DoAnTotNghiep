<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $request->user('web')?->hasRole(User::ROLE_OWNER) ? 'owner' : 'tenant';

        return ['id' => $this->id, 'mime_type' => $this->mime_type, 'byte_size' => $this->byte_size,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'url' => '/api/'.$role.'/maintenance-requests/'.$this->maintenance_request_id.'/photos/'.$this->id];
    }
}
