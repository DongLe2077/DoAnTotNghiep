<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class ChangeInvoiceRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['adjustment', 'due_date', 'cancel', 'restore'])],
            'amount' => ['required_if:action,adjustment', 'prohibited_unless:action,adjustment', 'regex:/^-?\d{1,16}(?:\.\d{1,2})?$/D', 'not_in:0,0.0,0.00,-0,-0.0,-0.00'],
            'due_date' => ['required_if:action,due_date', 'prohibited_unless:action,due_date', 'date_format:Y-m-d', 'regex:/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D'],
            'reason' => ['required', 'string', 'max:1000'],
            'version_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D'],
        ];
    }

    public function messages(): array
    {
        return ['amount.regex' => 'Khoản điều chỉnh là số tăng/giảm, tối đa 16 chữ số nguyên và 2 chữ số thập phân.',
            'amount.not_in' => 'Khoản điều chỉnh phải khác 0.',
            'due_date.after_or_equal' => 'Hạn thanh toán mới phải từ hôm nay trở đi.',
            'due_date.date_format' => 'Hạn thanh toán cần có định dạng năm-tháng-ngày.',
            'version_token.regex' => 'Phiên bản hóa đơn không hợp lệ.',
            'idempotency_key.regex' => 'Mã yêu cầu không hợp lệ.'] + parent::messages();
    }
}
