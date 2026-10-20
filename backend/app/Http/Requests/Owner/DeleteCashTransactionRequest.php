<?php

namespace App\Http\Requests\Owner;

class DeleteCashTransactionRequest extends OwnerRequest
{
    public function rules(): array
    {
        return ['version_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000']];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['version_token' => 'Phiên bản khoản thu chi'];
    }
}
