<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\CurrentWorkspace;
use App\Http\WorkspaceAccess;
use Illuminate\Foundation\Http\FormRequest;

class DomainStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        WorkspaceAccess::assertOperates($this->user(), CurrentWorkspace::get($this));

        return true;
    }

    public function rules(): array
    {
        return ['environment_uuid' => ['required', 'uuid:7'], 'hostname' => ['required', 'string', 'max:253']];
    }
}
