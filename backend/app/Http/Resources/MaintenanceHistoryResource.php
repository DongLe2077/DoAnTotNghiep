<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $request->user('web')->hasRole(User::ROLE_OWNER);
        $actor = $this->performed_by === null ? null : ['role' => $this->actor_role,
            'label' => $owner ? $this->actor_username : ($this->actor_role === User::ROLE_OWNER ? 'Chủ trọ' : 'Bạn')];
        if ($owner && $actor !== null) {
            $actor += ['id' => $this->performed_by, 'username' => $this->actor_username];
        }

        return ['id' => $this->id, 'action' => $this->action, 'revision' => $this->revision,
            'before' => ['status' => $this->before_status, 'resolution' => $this->before_resolution],
            'after' => ['status' => $this->after_status, 'resolution' => $this->after_resolution],
            'actor' => $actor, 'created_at' => $this->created_at->format('Y-m-d H:i:s')];
    }
}
