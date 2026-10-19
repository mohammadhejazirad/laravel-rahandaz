<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Access\OperationAccess;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SoftwareCapability
{
    public function available(): bool
    {
        $path = (string) config('rahandaz.software_capability_file');
        if (! is_file($path) || is_link($path) || fileowner($path) !== 0 || (fileperms($path) & 0022) !== 0) {
            return false;
        }
        $state = json_decode((string) file_get_contents($path), true);

        return ($state['schema_version'] ?? null) === '1' && ($state['enabled'] ?? null) === true;
    }

    public function catalog(): array
    {
        return json_decode((string) file_get_contents(base_path('infrastructure/operations/software/catalog.json')), true, flags: JSON_THROW_ON_ERROR)['packages'];
    }

    public function validate(array $packages): array
    {
        if (! array_is_list($packages) || count($packages) > 4 || count(array_unique($packages, SORT_REGULAR)) !== count($packages)
            || collect($packages)->contains(fn ($key) => ! is_string($key) || ! array_key_exists($key, $this->catalog()))) {
            throw ValidationException::withMessages(['packages' => 'نرم‌افزار انتخاب‌شده معتبر نیست.']);
        }
        sort($packages);

        return $packages;
    }

    public function request(Workspace $workspace, LocalHost $host, User $actor, array $packages, string $key, string $type = 'software.install', array $extra = []): OperationJob
    {
        abort_unless(in_array($type, ['software.install', 'setup.initialize'], true), 422);
        abort_unless((string) $host->workspace_id === (string) $workspace->id && $host->slug === 'local-host', 404);
        abort_unless(app(OperationAccess::class)->managesWorkspace($actor, $workspace->id), 403);
        if (! $this->available()) {
            throw ValidationException::withMessages(['packages' => 'سرویس نصب در دسترس نیست. وضعیت سرویس‌های راه‌انداز را بررسی کنید.']);
        }
        $packages = $this->validate($packages);
        if ($type === 'software.install' && $packages === []) {
            throw ValidationException::withMessages(['packages' => 'حداقل یک نرم‌افزار انتخاب کنید.']);
        }

        return DB::transaction(function () use ($workspace, $host, $actor, $packages, $key, $type, $extra): OperationJob {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['software.requests']);
            $active = OperationJob::query()->whereIn('type', ['software.install', 'setup.initialize'])
                ->whereIn('status', ['queued', 'running', 'waiting_retry'])->lockForUpdate()->first();
            if ($active !== null && ((string) $active->workspace_id !== (string) $workspace->id || (string) $active->actor_id !== (string) $actor->id || $active->idempotency_key !== $key || $active->type !== $type)) {
                throw ValidationException::withMessages(['packages' => 'یک عملیات نصب در حال انجام است. پس از پایان آن دوباره تلاش کنید.']);
            }
            $job = app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id, actorId: $actor->id, type: $type, idempotencyKey: $key,
                plan: ['contract_version' => '1', 'packages' => $packages, ...$extra], mutatesHost: true,
                resourceType: 'local_host', resourceId: $host->id,
            ));
            app(AuditRecorder::class)->record($workspace, $actor, 'software.install.requested', 'operation_job', $job->uuid,
                AuditResult::Success, ['packages' => $packages, 'host_executed' => false], operationJob: $job);

            return $job;
        });
    }

    public function latest(Workspace $workspace): ?OperationJob
    {
        return OperationJob::query()->where('workspace_id', $workspace->id)->whereIn('type', ['software.install', 'setup.initialize'])->latest('id')->first();
    }

    public function payload(?OperationJob $job): ?array
    {
        if ($job === null) {
            return null;
        }

        return ['uuid' => $job->uuid, 'status' => $job->status->value, 'message' => $job->error_code === null ? null : $this->errorMessage($job->error_code),
            'packages' => $job->steps()->where('key', 'gate')->first()?->checkpoint['packages'] ?? []];
    }

    public function errorMessage(string $code): string
    {
        return match ($code) {
            'PACKAGE_CONFLICT' => 'نصب این نرم‌افزار به تغییر بسته‌های موجود نیاز دارد. برای حفظ تنظیمات سرور، نصب متوقف شد.',
            'AUTHORIZATION_REVOKED', 'CANCELLED_BEFORE_EXECUTION' => 'مجوز درخواست تغییر کرده یا عملیات لغو شده است. درخواست را دوباره بررسی کنید.',
            'OS_NOT_AVAILABLE' => 'نصب نرم‌افزار روی این نسخهٔ سیستم‌عامل در دسترس نیست.',
            'INSTALL_INTERRUPTED' => 'نصب قطع شده است. وضعیت بسته‌ها را بررسی کنید و سپس دوباره تلاش کنید.',
            'SOFTWARE_BUSY' => 'نصب دیگری در حال اجراست. پس از پایان آن دوباره تلاش کنید.',
            default => 'بررسی یا نصب کامل نشد. اتصال مخزن بسته‌ها و سلامت سرویس‌های راه‌انداز را بررسی کنید و دوباره تلاش کنید.',
        };
    }
}
