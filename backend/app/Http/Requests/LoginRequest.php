<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'username.string' => 'Tên đăng nhập không hợp lệ.',
            'username.max' => 'Tên đăng nhập không được vượt quá 80 ký tự.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.string' => 'Mật khẩu không hợp lệ.',
            'password.max' => 'Mật khẩu không được vượt quá 255 ký tự.',
        ];
    }

    public function authenticate(): void
    {
        $key = $this->throttleKey();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            event(new Lockout($this));
            $seconds = RateLimiter::availableIn($key);

            throw new HttpResponseException(response()->json([
                'message' => "Bạn đăng nhập sai quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ], 429, ['Retry-After' => (string) $seconds]));
        }

        $authenticated = Auth::guard('web')->attemptWhen(
            $this->only('username', 'password'),
            fn (User $user) => $user->is_active && $user->hasRole(User::ROLE_OWNER, User::ROLE_TENANT),
        );

        if (! $authenticated) {
            RateLimiter::hit($key, 60);

            throw new HttpResponseException(response()->json([
                'message' => 'Tên đăng nhập hoặc mật khẩu không đúng, hoặc tài khoản không được phép đăng nhập.',
            ], 401));
        }

        RateLimiter::clear($key);
    }

    private function throttleKey(): string
    {
        return 'auth-login:'.hash('sha256', Str::transliterate(Str::lower((string) $this->input('username'))).'|'.$this->ip());
    }
}
