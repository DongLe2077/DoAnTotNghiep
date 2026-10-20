<?php

namespace App\Http\Requests\Owner;

class LeaveMemberRequest extends TenancyEventRequest
{
    public function rules(): array
    {
        return [
            'moved_out_at' => $this->timestamp(),
            'replacement_tenant_id' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
        ];
    }
}
