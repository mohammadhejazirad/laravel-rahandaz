<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\CurrentWorkspace;
use App\Http\WorkspaceAccess;
use Illuminate\Foundation\Http\FormRequest;

class BackupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        WorkspaceAccess::assertManagesSecrets($this->user(), CurrentWorkspace::get($this));

        return true;
    }

    public function rules(): array
    {
        return ['target_type' => ['required', 'in:local_host,project_database'], 'target_uuid' => ['required', 'uuid:7'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80', 'regex:/^[A-Za-z0-9_:-]+$/D']];
    }
}
