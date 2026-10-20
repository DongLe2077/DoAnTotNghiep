<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class RoomIndexRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'operational_status' => ['sometimes', 'required', Rule::in(['available', 'maintenance'])],
            'occupancy_status' => ['sometimes', 'required', Rule::in(['vacant', 'occupied', 'maintenance'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
