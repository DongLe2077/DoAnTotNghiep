<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class ServiceIndexRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_method' => ['sometimes', 'required', Rule::in(['metered', 'per_room'])],
            'is_active' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
