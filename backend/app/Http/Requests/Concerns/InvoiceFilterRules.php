<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait InvoiceFilterRules
{
    public function rules(): array
    {
        return [
            'billing_month' => ['sometimes', 'date_format:Y-m'],
            'room_id' => ['sometimes', 'integer', 'min:1'],
            'occupancy_id' => ['sometimes', 'integer', 'min:1'],
            'rental_group_id' => ['sometimes', 'integer', 'min:1'],
            'payment_status' => ['sometimes', Rule::in(['unpaid', 'paid', 'partially_paid', 'inconsistent', 'cancelled'])],
            'due_status' => ['sometimes', Rule::in(['overdue', 'upcoming', 'not_set'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
