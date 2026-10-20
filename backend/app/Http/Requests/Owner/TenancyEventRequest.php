<?php

namespace App\Http\Requests\Owner;

abstract class TenancyEventRequest extends OwnerRequest
{
    protected function timestamp(): array
    {
        return ['bail', 'required', 'date_format:Y-m-d H:i:s', 'before_or_equal:now'];
    }

    protected function members(): array
    {
        return [
            'tenant_ids' => ['required', 'array', 'min:1', 'max:100'],
            'tenant_ids.*' => ['bail', 'required', 'integer', 'min:1', 'distinct', 'exists:tenants,id'],
        ];
    }
}
