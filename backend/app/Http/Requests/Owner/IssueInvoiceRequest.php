<?php

namespace App\Http\Requests\Owner;

class IssueInvoiceRequest extends InvoicePreviewRequest
{
    public function rules(): array
    {
        return parent::rules() + ['preview_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']];
    }
}
