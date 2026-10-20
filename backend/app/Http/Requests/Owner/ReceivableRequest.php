<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class ReceivableRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'billing_month' => ['sometimes', 'date_format:Y-m', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D'],
            'room_id' => ['sometimes', 'integer', 'min:1'],
            'occupancy_id' => ['sometimes', 'integer', 'min:1'],
            'rental_group_id' => ['sometimes', 'integer', 'min:1'],
            'group_by' => ['sometimes', Rule::in(['room', 'rental_group'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
