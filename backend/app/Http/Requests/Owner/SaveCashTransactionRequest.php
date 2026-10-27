<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class SaveCashTransactionRequest extends OwnerRequest
{
    public function rules(): array
    {
        $fields = [
            'transaction_type' => ['required', Rule::in(['other_income', 'expense'])],
            'amount' => ['bail', 'required', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D', 'gt:0'],
            'occurred_at' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:now', 'after_or_equal:1000-01-01 00:00:00'],
            'description' => ['required', 'string', 'max:255'],
        ];
        if ($this->isMethod('POST')) {
            $fields['idempotency_key'] = ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D'];
        } else {
            foreach ($fields as &$rules) {
                array_unshift($rules, 'sometimes');
            }
            unset($rules);
            $fields['version_token'] = ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'];
            $fields['idempotency_key'] = ['prohibited'];
        }

        return $fields + [
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'revision' => ['prohibited'], 'deleted_at' => ['prohibited'],
            'recorded_by' => ['prohibited'], 'recorded_at' => ['prohibited'],
            'invoice_id' => ['prohibited'], 'payment_id' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'transaction_type' => 'Loại khoản thu chi', 'amount' => 'Số tiền', 'occurred_at' => 'Thời điểm phát sinh',
            'description' => 'Nội dung thu chi', 'version_token' => 'Phiên bản khoản thu chi',
            'idempotency_key' => 'Mã yêu cầu thu chi',
        ];
    }

    public function messages(): array
    {
        return ['amount.gt' => 'Số tiền phải lớn hơn 0.',
            'amount.regex' => 'Số tiền tối đa 16 chữ số nguyên và 2 chữ số thập phân, không dùng dấu phân cách hàng nghìn.',
            'version_token.regex' => 'Phiên bản khoản thu chi không hợp lệ.'] + parent::messages();
    }
}
