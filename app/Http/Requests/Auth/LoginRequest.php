<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Accounts\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private static ?string $dummyHash = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifier.required' => 'نام کاربری یا ایمیل را وارد کنید.',
            'password.required' => 'گذرواژه را وارد کنید.',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $password = (string) $this->string('password');
        $user = User::findForLogin((string) $this->string('identifier'));

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->burnPasswordTime($password, $user === null);
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'identifier' => 'نام کاربری یا گذرواژه نادرست است.',
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'identifier' => 'این حساب تعلیق شده است.',
            ]);
        }

        Auth::login($user);
        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'identifier' => 'تلاش‌های ورود بیش از حد است. کمی بعد دوباره تلاش کنید.',
        ])->status(429);
    }

    private function throttleKey(): string
    {
        return Str::lower((string) $this->string('identifier')).'|'.$this->ip();
    }

    private function burnPasswordTime(string $password, bool $missingUser): void
    {
        if (! $missingUser) {
            return;
        }

        self::$dummyHash ??= Hash::make(bin2hex(random_bytes(32)));
        Hash::check($password, self::$dummyHash);
    }
}
