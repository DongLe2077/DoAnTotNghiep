<?php

namespace App\Http\Requests\Owner;

class LinkMaintenanceExpenseRequest extends OwnerRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'cash_transaction_id' => ['required', 'integer', 'min:1'],
            'cash_version_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'version_token' => ['sometimes', 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D'],
            'performed_by' => ['prohibited'], 'actor_username' => ['prohibited'], 'amount' => ['prohibited'],
            'is_active' => ['prohibited'], 'revision' => ['prohibited'], 'maintenance_request_id' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['cash_transaction_id' => 'Khoản chi', 'cash_version_token' => 'Phiên bản khoản chi',
            'version_token' => 'Phiên bản liên kết', 'idempotency_key' => 'Mã yêu cầu'];
    }
}
