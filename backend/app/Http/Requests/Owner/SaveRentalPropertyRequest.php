<?php

namespace App\Http\Requests\Owner;

class SaveRentalPropertyRequest extends OwnerRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (\App\Services\RentalPropertyProfile::FIELDS as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $values[$field] = $value === '' && ! in_array($field, ['name', 'address'], true) ? null : $value;
            }
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', 'regex:/\S/u'],
            'address' => ['required', 'string', 'max:500', 'regex:/\S/u'],
            'contact_name' => ['present', 'nullable', 'string', 'max:150'],
            'phone' => ['present', 'nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]{3,30}$/D'],
            'email' => ['present', 'nullable', 'string', 'max:254', 'email:rfc'],
            'bank_name' => ['present', 'nullable', 'required_with:bank_account_number,bank_account_name', 'string', 'max:150'],
            'bank_account_number' => ['present', 'nullable', 'required_with:bank_name,bank_account_name', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9 .-]{2,49}$/D'],
            'bank_account_name' => ['present', 'nullable', 'required_with:bank_name,bank_account_number', 'string', 'max:150'],
            'notes' => ['present', 'nullable', 'string', 'max:2000'],
            'version_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'id' => ['prohibited'], 'revision' => ['prohibited'], 'updated_by' => ['prohibited'],
            'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return array_replace(parent::attributes(), ['name' => 'Tên nhà trọ', 'address' => 'Địa chỉ', 'contact_name' => 'Người liên hệ',
            'phone' => 'Điện thoại liên hệ', 'email' => 'Email liên hệ', 'bank_name' => 'Ngân hàng',
            'bank_account_number' => 'Số tài khoản', 'bank_account_name' => 'Tên chủ tài khoản', 'notes' => 'Ghi chú trên chứng từ']);
    }
}
