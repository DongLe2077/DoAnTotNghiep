<?php

namespace App\Http\Requests\Owner;

class UpdateRentalRateRequest extends OwnerRequest
{
    public function rules(): array
    {
        return ['default_monthly_rent' => ['bail', 'required', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D']];
    }
}
