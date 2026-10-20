<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait CashDateRules
{
    protected function dateRules(): array
    {
        return [
            'month' => ['sometimes', 'date_format:Y-m', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', 'prohibits:from,until'],
            'from' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after_or_equal:1000-01-01 00:00:00', 'prohibits:month'],
            'until' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after_or_equal:1000-01-01 00:00:00',
                'prohibits:month', Rule::when($this->filled('from'), 'after_or_equal:from')],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'month' => 'Tháng thu chi', 'from' => 'Thời điểm từ', 'until' => 'Thời điểm đến',
            'transaction_type' => 'Loại khoản thu chi', 'entry_type' => 'Nguồn thu chi',
            'group_by' => 'Cách nhóm thời gian',
        ];
    }

    public function messages(): array
    {
        return ['month.date_format' => 'Tháng thu chi cần có định dạng năm-tháng.',
            'prohibits' => 'Chỉ chọn tháng hoặc khoảng thời gian từ/đến.',
            'until.after_or_equal' => 'Thời điểm đến phải từ thời điểm bắt đầu trở đi.'] + parent::messages();
    }
}
