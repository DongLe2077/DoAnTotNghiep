<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\MaintenanceManagement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $request->user('web')?->hasRole(User::ROLE_OWNER) ?? false;

        return [
            'id' => $this->id, 'occupancy_id' => $this->occupancy_id,
            'room' => $this->whenLoaded('occupancy', fn () => $this->occupancy->room->only(['id', 'room_number'])),
            'group' => $this->whenLoaded('occupancy', fn () => $this->occupancy->rentalGroup->only(['id', 'group_code'])),
            'submitted_by' => $this->submitted_by, 'representative_assignment_id' => $this->representative_assignment_id,
            'reporter' => $this->when($owner && $this->relationLoaded('submittedBy'), fn () => $this->submittedBy->tenant?->only(['id', 'full_name', 'phone_number'])),
            'title' => $this->title, 'description' => $this->description, 'status' => $this->status,
            'status_label' => match ($this->status) {
                'submitted' => 'Đã gửi', 'in_progress' => 'Đang xử lý', 'resolved' => 'Đã xử lý', default => $this->status,
            },
            'resolution' => $this->resolution,
            'photos' => MaintenancePhotoResource::collection($this->whenLoaded('photos')),
            'submitted_at' => $this->submitted_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'can_process' => $owner && in_array($this->status, ['submitted', 'in_progress'], true),
            'version_token' => $this->when($owner, fn () => MaintenanceManagement::version($this->resource)),
        ];
    }
}
