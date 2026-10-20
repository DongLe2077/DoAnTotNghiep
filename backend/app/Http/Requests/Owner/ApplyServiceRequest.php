<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class ApplyServiceRequest extends TenancyEventRequest
{
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'effective_from' => $this->timestamp(),
            'unit_price' => ['sometimes', 'required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/D'],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'billing_method' => ['sometimes', 'required', Rule::in(['metered', 'per_room'])],
        ];
    }
}
