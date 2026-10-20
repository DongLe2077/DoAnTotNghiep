<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');

        return $user instanceof User && $user->is_active && $user->hasRole(User::ROLE_OWNER, User::ROLE_TENANT);
    }

    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', 'max:255'],
            'password' => ['bail', 'required', 'string', 'min:8', 'max:72', 'confirmed', function (string $attribute, mixed $value, Closure $fail) {
                if (strlen($value) > 72 || str_contains($value, "\0")) {
                    $fail('Mật khẩu không được vượt quá 72 byte hoặc chứa ký tự không hợp lệ.');
                }
            }],
            'id' => ['prohibited'], 'user_id' => ['prohibited'], 'tenant_id' => ['prohibited'],
            'username' => ['prohibited'], 'role' => ['prohibited'], 'is_active' => ['prohibited'], 'password_hash' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu mới cần ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu mới quá dài.',
            'password.confirmed' => 'Xác nhận mật khẩu mới chưa khớp.',
            'prohibited' => 'Không được thay đổi thông tin tài khoản qua chức năng đổi mật khẩu.',
        ];
    }
}
