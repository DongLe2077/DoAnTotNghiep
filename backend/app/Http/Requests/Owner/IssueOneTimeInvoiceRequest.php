<?php

namespace App\Http\Requests\Owner;

class IssueOneTimeInvoiceRequest extends OneTimeInvoicePreviewRequest
{
    public function rules(): array
    {
        return parent::rules() + IssueInvoiceRequest::publicationRules() + [
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D'],
        ];
    }
}
