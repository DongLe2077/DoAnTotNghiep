<?php

namespace App\Http\Requests\Owner;

class UnlinkMaintenanceExpenseRequest extends OwnerRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return ['version_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D'],
            'performed_by' => ['prohibited'], 'actor_username' => ['prohibited'], 'amount' => ['prohibited'],
            'is_active' => ['prohibited'], 'revision' => ['prohibited'], 'maintenance_request_id' => ['prohibited'],
            'cash_transaction_id' => ['prohibited']];
    }
}
