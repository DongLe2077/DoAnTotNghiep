<?php

namespace App\Http\Requests\Owner;

class AddMembersRequest extends TenancyEventRequest
{
    public function rules(): array
    {
        return $this->members() + ['moved_in_at' => $this->timestamp()];
    }
}
