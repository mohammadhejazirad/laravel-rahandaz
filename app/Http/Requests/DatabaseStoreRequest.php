<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\CurrentWorkspace;
use App\Http\WorkspaceAccess;
use Illuminate\Foundation\Http\FormRequest;

class DatabaseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        WorkspaceAccess::assertManagesSecrets($this->user(), CurrentWorkspace::get($this));

        return true;
    }

    public function rules(): array
    {
        return ['instance_uuid' => ['required', 'uuid:7'], 'environment_uuid' => ['required', 'uuid:7'],
            'name' => ['required', 'regex:/^[a-z_][a-z0-9_]{0,62}$/D'], 'username' => ['required', 'regex:/^[a-z_][a-z0-9_]{0,62}$/D'],
            'password' => ['required', 'string', 'min:16', 'max:256']];
    }
}
