<?php

namespace App\Http\Requests\Owner;

class TransferOccupancyRequest extends TenancyEventRequest
{
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'transferred_at' => $this->timestamp(),
            'representative_tenant_id' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
        ];
    }
}
