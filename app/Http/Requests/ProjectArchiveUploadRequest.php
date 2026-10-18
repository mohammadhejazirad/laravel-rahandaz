<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Environment;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProjectArchiveUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $project = $this->route('project');
        $environment = $this->route('environment');
        $membership = $user?->currentMembership();

        return $user !== null
            && $project instanceof Project
            && $environment instanceof Environment
            && $membership !== null
            && (string) $membership->workspace_id === (string) $project->workspace_id
            && (string) $environment->project_id === (string) $project->id
            && $user->can('deploy', $project);
    }

    public function rules(): array
    {
        $maxKilobytes = (int) ceil(((int) config('rahandaz.project_archives.max_upload_bytes')) / 1024);

        return [
            'archive' => ['required', 'file', 'max:'.$maxKilobytes],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $archive = $this->file('archive');

                if ($archive === null) {
                    return;
                }

                $name = $archive->getClientOriginalName();
                $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

                if ($extension !== 'zip') {
                    $validator->errors()->add('archive', 'فقط فایل با پسوند ZIP پذیرفته می‌شود.');
                }

                if ($name === '' || strlen($name) > 255 || preg_match('/[\\x00-\\x1F\\x7F\\/\\\\]/', $name) === 1) {
                    $validator->errors()->add('archive', 'نام فایل ZIP نامعتبر است.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'archive.required' => 'یک فایل ZIP انتخاب کنید.',
            'archive.file' => 'فایل بارگذاری‌شده معتبر نیست.',
            'archive.max' => 'حجم فایل ZIP بیشتر از حد مجاز است.',
            'archive.uploaded' => 'ارسال فایل کامل نشد؛ محدودیت PHP یا وب‌سرور را بررسی کنید.',
        ];
    }
}
