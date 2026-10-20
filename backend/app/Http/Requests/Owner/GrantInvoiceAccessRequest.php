<?php

namespace App\Http\Requests\Owner;

class GrantInvoiceAccessRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'representative_assignment_id' => ['required', 'integer', 'min:1'],
            'grant_reason' => ['required', 'string', 'max:255'],
            'user_id' => ['prohibited'],
            'occupancy_id' => ['prohibited'],
        ];
    }
}
