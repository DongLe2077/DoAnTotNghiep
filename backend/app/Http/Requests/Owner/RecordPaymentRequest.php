<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class RecordPaymentRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D', 'gt:0'],
            'paid_at' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:now', 'after_or_equal:1000-01-01 00:00:00'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer'])],
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D'],
            'invoice_id' => ['prohibited'],
            'recorded_by' => ['prohibited'],
            'recorded_at' => ['prohibited'],
            'payment_status' => ['prohibited'],
            'outstanding_amount' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'amount' => 'Số tiền thu', 'paid_at' => 'Thời điểm thu tiền',
            'payment_method' => 'Hình thức thu tiền', 'idempotency_key' => 'Mã yêu cầu thu tiền',
            'invoice_id' => 'Hóa đơn', 'recorded_by' => 'Người ghi nhận', 'recorded_at' => 'Thời điểm ghi nhận',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => 'Số tiền thu tối đa 16 chữ số nguyên và 2 chữ số thập phân; không dùng dấu phân cách hàng nghìn.',
            'amount.gt' => 'Số tiền thu phải lớn hơn 0.',
            'idempotency_key.regex' => 'Mã yêu cầu cần 8–64 ký tự ASCII gồm chữ, số hoặc . _ : -, bắt đầu bằng chữ hoặc số.',
        ] + parent::messages();
    }
}
