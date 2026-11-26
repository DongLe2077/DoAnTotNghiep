<?php

namespace App\Http\Requests\Owner;

class OneTimeInvoicePreviewRequest extends InvoicePreviewRequest
{
    public function rules(): array
    {
        return [
            'service_ids' => ['prohibited'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'regex:/^[0-9]{1,14}(?:\.[0-9]{1,2})?$/D', 'gt:0'],
        ] + parent::rules();
    }

    public function attributes(): array
    {
        return parent::attributes() + ['description' => 'Nội dung khoản thu', 'amount' => 'Số tiền'];
    }
}
