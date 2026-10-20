<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\CashDateRules;
use Illuminate\Validation\Rule;

class CashTransactionIndexRequest extends OwnerRequest
{
    use CashDateRules;

    public function rules(): array
    {
        return $this->dateRules() + [
            'state' => ['sometimes', Rule::in(['active', 'deleted', 'all'])],
            'transaction_type' => ['sometimes', Rule::in(['other_income', 'expense'])],
            'q' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
