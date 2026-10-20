<?php

namespace App\Http\Requests\Owner;

use Closure;
use Illuminate\Validation\Rule;

class TenantAccountRequest extends OwnerRequest
{
    public function rules(): array
    {
        $account = $this->route('tenant')->user;
        $unique = Rule::unique('users', 'username');
        if ($account !== null) {
            $unique->ignore($account);
        }
        $presence = $account === null ? 'required' : 'sometimes';

        return [
            'username' => ['bail', $presence, 'required', 'string', 'max:80', $unique],
            'password' => ['bail', $presence, 'required', 'string', 'min:8', 'max:72', 'confirmed', function (string $attribute, mixed $value, Closure $fail) {
                if (strlen($value) > 72) {
                    $fail('Mật khẩu quá dài. Vui lòng dùng mật khẩu ngắn hơn.');
                }
                if (str_contains($value, "\0")) {
                    $fail('Mật khẩu chứa ký tự không hợp lệ.');
                }
            }],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
