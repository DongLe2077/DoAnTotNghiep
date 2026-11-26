<?php

namespace App\Http\Requests;

use App\Services\Tenancy\PropertyContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(app(PropertyContext::class)->enabled(), 404);
        abort_if($this->user('web'), 409, 'Bạn đang đăng nhập. Hãy đăng xuất trước khi tạo tài khoản mới.');

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => Str::lower(trim($this->input('username')))]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/D'],
            'username' => ['bail', 'required', 'string', 'min:4', 'max:80', 'regex:/^[a-z0-9][a-z0-9._-]*$/D', 'unique:users,username'],
            'password' => [
                'bail', 'required', 'string', 'min:8', 'confirmed',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (strlen($value) > 72 || str_contains($value, "\0")) {
                        $fail('Mật khẩu dài tối đa 72 byte và không chứa ký tự rỗng.');
                    }
                },
            ],
            'role' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'property_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            'owner_user_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Vui lòng nhập họ và tên.',
            'contact_phone.regex' => 'Nhập số điện thoại gồm 8–15 chữ số, có thể bắt đầu bằng dấu +.',
            'username.regex' => 'Tên đăng nhập dùng chữ không dấu, số, dấu chấm, gạch dưới hoặc gạch ngang.',
            'username.unique' => 'Tên đăng nhập này đã có người dùng.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'password.confirmed' => 'Hai mật khẩu chưa khớp.',
        ];
    }
}
