<?php

namespace App\Http\Requests\Tenant;

class StayIndexRequest extends TenantRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100']];
    }
}
