<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone_number' => $this->phone_number,
            'identity_number' => $this->identity_number,
            'account' => $this->whenLoaded('user', fn () => new AuthUserResource($this->user)),
            'current_stay' => $this->whenLoaded('stays', fn () => $this->stays->isEmpty() ? null : new TenantStayResource($this->stays->first())),
            'history_count' => (int) $this->stays_count,
            'can_delete' => (int) $this->stays_count === 0 && $this->relationLoaded('user') && $this->user === null,
        ];
    }
}
