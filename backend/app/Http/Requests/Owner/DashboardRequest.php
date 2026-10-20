<?php

namespace App\Http\Requests\Owner;

class DashboardRequest extends OwnerRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('month')) {
            $this->merge(['month' => now()->format('Y-m')]);
        }
    }

    public function rules(): array
    {
        return ['month' => ['required', 'date_format:Y-m', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D']];
    }
}
