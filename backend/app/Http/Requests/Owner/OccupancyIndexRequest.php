<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class OccupancyIndexRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['active', 'ended'])],
            'room_id' => ['sometimes', 'integer', 'min:1'],
            'rental_group_id' => ['sometimes', 'integer', 'min:1'],
            'tenant_id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
