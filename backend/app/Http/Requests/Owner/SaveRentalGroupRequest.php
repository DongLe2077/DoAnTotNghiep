<?php

namespace App\Http\Requests\Owner;

use App\Models\RentalGroup;
use Illuminate\Validation\Rule;

class SaveRentalGroupRequest extends OwnerRequest
{
    public function rules(): array
    {
        $unique = Rule::unique('rental_groups', 'group_code');
        if ($this->route('rentalGroup') instanceof RentalGroup) {
            $unique->ignore($this->route('rentalGroup'));
        }

        return ['group_code' => ['bail', 'required', 'string', 'max:40', $unique]];
    }
}
