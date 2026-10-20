<?php

namespace App\Http\Requests\Owner;

class RestoreBillingRepresentativeRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'tenant_stay_id' => ['required', 'integer', 'min:1'],
            'started_at' => ['prohibited'], 'ended_at' => ['prohibited'],
            'occupancy_id' => ['prohibited'], 'user_id' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['tenant_stay_id' => 'Lần cư trú của người đại diện'];
    }
}
