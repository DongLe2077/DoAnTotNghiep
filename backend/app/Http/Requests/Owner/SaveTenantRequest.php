<?php

namespace App\Http\Requests\Owner;

use App\Models\Tenant;
use Illuminate\Validation\Rule;

class SaveTenantRequest extends OwnerRequest
{
    public function rules(): array
    {
        $unique = Rule::unique('tenants', 'identity_number');
        if ($this->route('tenant') instanceof Tenant) {
            $unique->ignore($this->route('tenant'));
        }
        $rules = [
            'full_name' => ['bail', 'required', 'string', 'max:150'],
            'phone_number' => ['bail', 'required', 'string', 'max:20'],
            'identity_number' => ['bail', 'sometimes', 'nullable', 'string', 'max:30', $unique],
        ];
        if (! $this->isMethod('POST')) {
            array_unshift($rules['full_name'], 'sometimes');
            array_unshift($rules['phone_number'], 'sometimes');
        }

        return $rules;
    }
}
