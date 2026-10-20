<?php

namespace App\Http\Requests\Owner;

use App\Models\Service;
use Illuminate\Validation\Rule;

class SaveServiceRequest extends OwnerRequest
{
    public function rules(): array
    {
        $uniqueCode = Rule::unique('services', 'service_code');
        if ($this->route('service') instanceof Service) {
            $uniqueCode->ignore($this->route('service'));
        }

        $rules = [
            'service_code' => ['bail', 'required', 'string', 'max:40', $uniqueCode],
            'name' => ['bail', 'required', 'string', 'max:100'],
            'unit' => ['bail', 'required', 'string', 'max:30'],
            'billing_method' => ['required', Rule::in(['metered', 'per_room'])],
            'default_unit_price' => ['bail', 'required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/D'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];

        if (! $this->isMethod('POST')) {
            foreach (['service_code', 'name', 'unit', 'billing_method', 'default_unit_price'] as $field) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        return $rules;
    }
}
