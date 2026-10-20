<?php

namespace App\Http\Requests\Owner;

class CheckInRequest extends TenancyEventRequest
{
    public function rules(): array
    {
        return $this->members() + [
            'rental_group_id' => ['required', 'integer', 'exists:rental_groups,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'started_at' => $this->timestamp(),
            'monthly_rent' => ['prohibited'],
            'representative_tenant_id' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
        ];
    }
}
