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

    public function categories(): array
    {
        return json_decode((string) file_get_contents(base_path('infrastructure/operations/software/catalog.json')), true, flags: JSON_THROW_ON_ERROR)['categories'];
    }

    public function setupCatalog(): array
    {
        return array_filter($this->catalog(), fn ($item) => $item['setup'] ?? false);
    }

    public function validateSetup(array $packages): array
    {
        $packages = $this->validate($packages);
        if (array_diff($packages, array_keys($this->setupCatalog())) !== []) {
            throw ValidationException::withMessages(['packages' => 'این نرم‌افزار را پس از راه‌اندازی از کاتالوگ نصب کنید.']);
        }

        return $packages;
    }

    public function installed(Workspace $workspace): array
    {
        $results = [];
        // Only previously verified fixed paths; the panel never accepts paths from HTTP.
        $jobs = OperationJob::query()->where('workspace_id', $workspace->id)->whereIn('type', ['software.install', 'setup.initialize'])
            ->whereIn('status', ['succeeded', 'failed'])->with('steps')->latest('id')->limit(200)->get();
        foreach ($jobs as $job) {
            foreach ($job->steps->firstWhere('key', 'gate')?->checkpoint['packages'] ?? [] as $row) {
                $key = $row['package'] ?? '';
                $entry = $this->catalog()[$key] ?? [];
                if (! isset($results[$key]) && ($row['verified'] ?? false) === true && isset($row['executable_path'])
                    && $row['executable_path'] === ($entry['executable_path'] ?? null)) {
                    $results[$key] = $row;
                }
            }
        }

        return array_values($results);
    }

    public function validate(array $packages): array
    {
        if (! array_is_list($packages) || count($packages) > 32 || count(array_unique($packages, SORT_REGULAR)) !== count($packages)
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
        $packages = $type === 'setup.initialize' ? $this->validateSetup($packages) : $this->validate($packages);
        if ($type === 'software.install' && $packages === []) {
            throw ValidationException::withMessages(['packages' => 'حداقل یک نرم‌افزار انتخاب کنید.']);
        }

        return DB::transaction(function () use ($workspace, $host, $actor, $packages, $key, $type, $extra): OperationJob {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['software.requests']);
            $active = OperationJob::query()->whereIn('type', ['software.install', 'setup.initialize'])
                ->whereNotIn('status', ['succeeded', 'failed', 'cancelled'])->lockForUpdate()->first();
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

        $checkpoint = $job->steps()->where('key', 'gate')->first()?->checkpoint ?? [];
        $packages = $checkpoint['packages'] ?? array_map(fn ($key) => ['package' => $key, 'status' => $job->status->isTerminal() ? 'skipped' : 'queued', 'phase' => 'pending', 'version' => null, 'verified' => false, 'changed' => false, 'error_code' => $job->error_code], $job->plan['input']['packages'] ?? []);
        $packages = array_map(function ($item): array {
            $item['status'] ??= ($item['verified'] ?? false) ? 'succeeded' : 'queued';
            $item['message'] = empty($item['error_code']) ? null : $this->errorMessage($item['error_code']);
            $item['phase_label'] = $this->phaseLabel($item['phase'] ?? 'pending');

            return $item;
        }, $packages);
        $summary = ['total' => count($packages)];
        foreach (['succeeded', 'failed', 'skipped'] as $status) {
            $summary[$status] = count(array_filter($packages, fn ($item) => $item['status'] === $status));
        }

        return ['uuid' => $job->uuid, 'status' => $job->status->value, 'message' => $job->error_code === null ? null : $this->errorMessage($job->error_code),
            'summary' => $summary, 'packages' => $packages];
    }

    public function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'control_plane' => 'بررسی سلامت پنل و دسترسی‌ها',
            'preflight' => 'بررسی پیش‌نیاز و تداخل‌ها',
            'dependency_plan' => 'بررسی بسته‌ها و تثبیت نسخه‌ها',
            'download' => 'دریافت و بررسی صحت فایل‌ها',
            'installation' => 'نصب',
            'verification' => 'بررسی نسخه و اجرای واقعی',
            'verified' => 'تأیید نهایی بسته',
            'final_health' => 'بررسی دوبارهٔ سلامت پنل',
            'failed' => 'توقف با خطا',
            default => 'در انتظار اجرا',
        };
    }

    public function errorMessage(string $code): string
    {
        return match ($code) {
            'PACKAGE_CONFLICT' => 'وابستگی‌ها با بسته‌های موجود تداخل دارند یا به ارتقا/حذف آن‌ها نیاز دارند. فهرست مخزن و نسخه‌های نصب‌شده را بررسی کنید. ارتقا یا حذف خودکار انجام نشد؛ بسته‌های تازهٔ مراحل قبلی ممکن است نصب شده باشند.',
            'PACKAGE_MANAGER_BUSY' => 'مدیر بسته‌ها در اختیار فرایند دیگری مانند به‌روزرسانی خودکار Ubuntu است. پس از پایان آن دوباره تلاش کنید؛ قفل APT را حذف نکنید.',
            'PACKAGE_DOWNLOAD_FAILED', 'DOWNLOAD_FAILED' => 'دریافت فایل کامل نشد. DNS، اینترنت سرور، دسترسی به مخزن و فضای آزاد را بررسی کنید و دوباره تلاش کنید.',
            'DOWNLOAD_ACCESS_DENIED' => 'منبع دانلود دسترسی این سرور را رد کرد (مانند محدودیت منطقه‌ای یا HTTP 403). دسترسی IP سرور به منبع رسمی را بررسی کنید؛ هیچ فایل تأییدنشده‌ای اجرا نشد.',
            'DOWNLOAD_RATE_LIMITED' => 'منبع دانلود تعداد درخواست‌ها را محدود کرده است. کمی بعد دوباره تلاش کنید.',
            'PACKAGE_SIGNATURE_INVALID', 'CHECKSUM_MISMATCH' => 'امضا یا checksum فایل معتبر نیست. برای حفظ امنیت نصب متوقف شد؛ منبع و نسخهٔ فایل باید بررسی شوند.',
            'PACKAGE_VERSION_UNAVAILABLE' => 'نسخهٔ مورد نیاز در فهرست مخزن فعلی پیدا نشد. دسترسی و تازگی index مخزن سازگار با Ubuntu را بررسی کنید.',
            'PACKAGE_PLAN_CHANGED' => 'وضعیت بسته‌ها پس از دریافت تغییر کرده است. برای جلوگیری از نصب با برنامهٔ متفاوت، ادامه متوقف شد؛ پس از پایان سایر عملیات APT دوباره تلاش کنید.',
            'SOFTWARE_VERIFICATION_FAILED' => 'شاهد معتبر و مربوط به همین درخواست برای تأیید پایان نصب دریافت نشد. گزارش helper و صف را بررسی کنید؛ موفقیت فرض نشده است.',
            'PACKAGE_STATE_UNSAFE' => 'یک عملیات dpkg قبلی کامل نشده است. وضعیت بسته‌های سرور باید توسط مدیر بررسی شود؛ تعمیر خودکار انجام نشد.',
            'INSUFFICIENT_DISK' => 'فضای آزاد دیسک برای دریافت و نصب کافی نیست. فضای لازم را آزاد کنید و درخواست جدید بسازید.',
            'INSUFFICIENT_MEMORY' => 'حافظهٔ آزاد برای این نرم‌افزار کافی نیست. بار سرور و نیاز حافظهٔ بسته را بررسی کنید.',
            'PACKAGE_VERIFICATION_FAILED', 'RENDER_VERIFICATION_FAILED' => 'فایل نصب شد یا از قبل وجود داشت، اما اجرای آزمایشی یا بررسی نسخه تأیید نشد. وابستگی‌ها و گزارش همین بسته را بررسی کنید؛ موفقیت نصب اعلام نشده است.',
            'PACKAGE_SERVICE_FAILED' => 'بسته نصب است اما سرویس آن فعال نیست. تنظیمات سرویس، فضای دیسک و journal همان سرویس را بررسی کنید؛ موفقیت نهایی اعلام نشد.',
            'PACKAGE_COMMAND_FAILED' => 'مدیر بسته‌ها نصب را کامل نکرد. وضعیت dpkg، فضای دیسک و سازگاری وابستگی‌ها را بررسی کنید؛ امکان نصب بخشی از وابستگی‌ها وجود دارد.',
            'COMMAND_TIMEOUT' => 'مهلت مرحله تمام شد. اتصال مخزن و بار سرور را بررسی کنید؛ پیش از تلاش دوباره وضعیت بسته‌ها را ببینید.',
            'PORT_IN_USE' => 'پورت پیش‌فرض این سرویس در اختیار سرویس دیگری است. سرویس موجود متوقف یا جایگزین نشد.',
            'SERVICE_CONFLICT' => 'یک سرویس یا نصب موجود با این بسته تداخل دارد. برای حفظ داده‌ها جایگزینی خودکار انجام نشد.',
            'SANDBOX_UNAVAILABLE', 'SANDBOX_POLICY_CONFLICT' => 'sandbox مرورگر آماده نیست یا با تنظیم موجود AppArmor تداخل دارد. پشتیبانی user namespace و profile مرورگر را بررسی کنید؛ sandbox غیرفعال نمی‌شود.',
            'CPU_FEATURE_UNAVAILABLE' => 'پردازنده ویژگی AVX مورد نیاز MongoDB را ندارد. مشخصات CPU ماشین مجازی را از ارائه‌دهنده بررسی کنید.',
            'ARCHIVE_UNSAFE' => 'ساختار فایل نصب معتبر نیست. نصب بدون استخراج فایل ناامن متوقف شد.',
            'CORE_SERVICE_MISSING' => 'یکی از سرویس‌های اصلی پنل موجود نیست. ابتدا نصب اصلی پنل را بررسی کنید؛ جایگزینی runtime پنل از کاتالوگ انجام نمی‌شود.',
            'ARCHITECTURE_UNAVAILABLE' => 'این بسته برای معماری فعلی سرور در این نسخه ارائه نشده است.',
            'UNSAFE_DESTINATION', 'UNSAFE_CODE', 'UNSAFE_STATE' => 'مالکیت یا دسترسی مسیر نصب امن نیست، یا فایل خارجی در مقصد وجود دارد. مسیر باید توسط مدیر بررسی شود؛ بازنویسی انجام نشد.',
            'AUTHORIZATION_CHECK_FAILED', 'SOFTWARE_HELPER_UNAVAILABLE' => 'ارتباط یا بررسی مجوز سرویس نصب کامل نشد. وضعیت helper، صف، پایگاه داده و سرویس‌های پنل را بررسی کنید.',
            'AUTHORIZATION_REVOKED', 'CANCELLED_BEFORE_EXECUTION' => 'مجوز درخواست تغییر کرده یا عملیات لغو شده است. درخواست را دوباره بررسی کنید.',
            'OS_NOT_AVAILABLE' => 'نصب نرم‌افزار روی این نسخهٔ سیستم‌عامل در دسترس نیست.',
            'INSTALL_INTERRUPTED' => 'نصب قطع شده است. وضعیت بسته‌ها را بررسی کنید و سپس دوباره تلاش کنید.',
            'SOFTWARE_BUSY' => 'نصب دیگری در حال اجراست. پس از پایان آن دوباره تلاش کنید.',
            default => 'بررسی یا نصب کامل نشد. اتصال مخزن بسته‌ها و سلامت سرویس‌های راه‌انداز را بررسی کنید و دوباره تلاش کنید.',
        };
    }
}
