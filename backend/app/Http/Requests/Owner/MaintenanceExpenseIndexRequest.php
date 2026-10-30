<?php

namespace App\Http\Requests\Owner;

class MaintenanceExpenseIndexRequest extends OwnerRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50']];
    }
}
