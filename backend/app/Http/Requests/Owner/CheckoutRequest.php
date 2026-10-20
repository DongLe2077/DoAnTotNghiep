<?php

namespace App\Http\Requests\Owner;

class CheckoutRequest extends TenancyEventRequest
{
    public function rules(): array
    {
        return ['ended_at' => $this->timestamp()];
    }
}
