<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\CashDateRules;
use Illuminate\Validation\Rule;

class CashFlowRequest extends OwnerRequest
{
    use CashDateRules;

    protected function prepareForValidation(): void
    {
        if (! $this->hasAny(['month', 'from', 'until'])) {
            $this->merge(['month' => now()->format('Y-m')]);
        }
    }

    public function rules(): array
    {
        return $this->dateRules() + [
            'entry_type' => ['sometimes', Rule::in(['invoice_payment', 'other_income', 'expense'])],
            'q' => ['sometimes', 'string', 'max:255'],
            'group_by' => ['sometimes', Rule::in(['day', 'month'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
