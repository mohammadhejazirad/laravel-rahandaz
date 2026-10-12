<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'گذرواژه فعلی را وارد کنید.',
            'current_password.current_password' => 'گذرواژه فعلی نادرست است.',
            'password.required' => 'گذرواژه جدید را وارد کنید.',
            'password.confirmed' => 'تکرار گذرواژه با گذرواژه جدید یکسان نیست.',
            'password.min' => 'گذرواژه باید حداقل ۱۲ نویسه باشد.',
            'password.letters' => 'گذرواژه باید شامل حرف باشد.',
            'password.mixed' => 'گذرواژه باید حرف کوچک و بزرگ داشته باشد.',
            'password.numbers' => 'گذرواژه باید شامل عدد باشد.',
        ];
    }
}
