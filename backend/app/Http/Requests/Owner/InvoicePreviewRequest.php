<?php

namespace App\Http\Requests\Owner;

class InvoicePreviewRequest extends OwnerRequest
{
    public function attributes(): array
    {
        return parent::attributes() + ['billing_month' => 'Tháng tính tiền', 'preview_token' => 'Mã xem trước hóa đơn'];
    }

    public function messages(): array
    {
        return ['billing_month.date_format' => 'Tháng tính tiền cần có định dạng năm-tháng.',
            'billing_month.regex' => 'Tháng tính tiền không hợp lệ.',
            'preview_token.regex' => 'Mã xem trước hóa đơn không hợp lệ.'] + parent::messages();
    }

    public function rules(): array
    {
        return [
            'billing_month' => ['required', 'date_format:Y-m', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/', 'before_or_equal:'.now()->format('Y-m')],
            'items' => ['prohibited'],
            'monthly_rent' => ['prohibited'],
            'total_amount' => ['prohibited'],
            'issued_at' => ['prohibited'],
            'issued_by' => ['prohibited'],
            'representative_assignment_id' => ['prohibited'],
        ];
    }
}
