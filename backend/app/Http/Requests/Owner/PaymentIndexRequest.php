<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class PaymentIndexRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'invoice_id' => ['sometimes', 'integer', 'min:1'],
            'room_id' => ['sometimes', 'integer', 'min:1'],
            'rental_group_id' => ['sometimes', 'integer', 'min:1'],
            'payment_method' => ['sometimes', Rule::in(['cash', 'bank_transfer'])],
            'status' => ['sometimes', Rule::in(['active', 'voided'])],
            'paid_from' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after_or_equal:1000-01-01 00:00:00'],
            'paid_until' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after_or_equal:1000-01-01 00:00:00', Rule::when($this->filled('paid_from'), 'after_or_equal:paid_from')],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
