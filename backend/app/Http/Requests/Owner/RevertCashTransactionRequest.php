<?php

namespace App\Http\Requests\Owner;

class RevertCashTransactionRequest extends OwnerRequest
{
    public function rules(): array
    {
        return ['history_id' => ['required', 'integer', 'min:1'],
            'version_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'reason' => ['required', 'string', 'max:1000']];
    }
}
