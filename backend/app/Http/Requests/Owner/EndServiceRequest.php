<?php

namespace App\Http\Requests\Owner;

class EndServiceRequest extends TenancyEventRequest
{
    public function rules(): array
    {
        return ['effective_until' => $this->timestamp()];
    }
}
