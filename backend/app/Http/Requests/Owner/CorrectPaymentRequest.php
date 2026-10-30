<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class CorrectPaymentRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['void', 'replace'])],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
            'version_token' => ['required', 'string', 'size:64'],
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D'],
            'replacement' => ['required_if:action,replace', 'prohibited_unless:action,replace', 'array:amount,paid_at,payment_method'],
            'replacement.amount' => ['required_if:action,replace', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D', 'gt:0'],
            'replacement.paid_at' => ['required_if:action,replace', 'date_format:Y-m-d H:i:s', 'before_or_equal:now', 'after_or_equal:1000-01-01 00:00:00'],
            'replacement.payment_method' => ['required_if:action,replace', Rule::in(['cash', 'bank_transfer'])],
            'invoice_id' => ['prohibited'], 'payment_id' => ['prohibited'], 'performed_by' => ['prohibited'],
            'created_at' => ['prohibited'], 'before_data' => ['prohibited'], 'after_data' => ['prohibited'],
            'replacement_payment_id' => ['prohibited'], 'actor_username' => ['prohibited'], 'request_fingerprint' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['action' => 'Cách sửa phiếu thu', 'reason' => 'Lý do sửa sai',
            'replacement.amount' => 'Số tiền đúng', 'replacement.paid_at' => 'Thời điểm nhận tiền đúng',
            'replacement.payment_method' => 'Hình thức nhận tiền đúng', 'version_token' => 'Phiên bản phiếu thu'];
    }
}
