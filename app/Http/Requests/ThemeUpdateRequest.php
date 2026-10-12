<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Accounts\Enums\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ThemeUpdateRequest extends FormRequest
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
            'theme' => ['required', Rule::enum(Theme::class)],
        ];
    }
}
