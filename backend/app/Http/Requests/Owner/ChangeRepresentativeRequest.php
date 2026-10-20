<?php

namespace App\Http\Requests\Owner;

class ChangeRepresentativeRequest extends TenancyEventRequest
{
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'started_at' => $this->timestamp(),
        ];
    }
}
