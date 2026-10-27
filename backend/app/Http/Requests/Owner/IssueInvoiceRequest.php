<?php

namespace App\Http\Requests\Owner;

class IssueInvoiceRequest extends InvoicePreviewRequest
{
    public function rules(): array
    {
        return parent::rules() + ['preview_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'regex:/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', 'after_or_equal:'.now()->format('Y-m-d')]];
    }
}
