<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\CurrentWorkspace;
use App\Http\WorkspaceAccess;
use Illuminate\Foundation\Http\FormRequest;

class DeploymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        WorkspaceAccess::assertOperates($this->user(), CurrentWorkspace::get($this));

        return true;
    }

    public function rules(): array
    {
        return [
            'environment_uuid' => ['required', 'uuid:7'],
            'commit_sha' => ['required', 'regex:/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/D'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80', 'regex:/^[A-Za-z0-9_:-]+$/D'],
        ];
    }
}
