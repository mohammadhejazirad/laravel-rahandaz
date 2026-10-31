<?php

declare(strict_types=1);

namespace App\Domain\Docker;

use App\Domain\Access\OperationAccess;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Infrastructure\Secrets\CredentialStore;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DockerStackControl
{
    public function __construct(
        private readonly DockerAppCatalog $apps,
        private readonly ComposeEnvReader $env,
        private readonly CredentialStore $credentials,
    ) {}

    /**
     * @param  array<string, string>  $values
     */
    public function request(Workspace $workspace, LocalHost $host, User $actor, string $slug, ?string $template, ?string $compose, array $values): OperationJob
    {
        abort_unless((string) $host->workspace_id === (string) $workspace->id && $host->slug === 'local-host', 404);
        abort_unless(app(OperationAccess::class)->managesWorkspace($actor, $workspace->id), 403);
        if (preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $slug) !== 1 || str_starts_with($slug, 'rahandaz')) {
            throw ValidationException::withMessages(['slug' => 'نام استک فقط حروف کوچک، عدد و خط تیره است و نام راه‌انداز پذیرفته نمی‌شود.']);
        }

        $app = $template === null || $template === '' ? null : $this->apps->find($template);
        if ($template !== null && $template !== '' && $app === null) {
            throw ValidationException::withMessages(['template' => 'این برنامهٔ آماده در فهرست نیست.']);
        }
        $body = $app['compose'] ?? $compose ?? '';
        if (strlen($body) < 10 || strlen($body) > 32768) {
            throw ValidationException::withMessages(['compose' => 'متن Compose باید بین ۱۰ و ۳۲۷۶۸ نویسه باشد.']);
        }
        if ($this->env->hasInlineSecret($body)) {
            throw ValidationException::withMessages(['compose' => 'رمز را داخل فایل ننویسید. از ${NAME} استفاده کنید تا در env جدا نگهداری شود.']);
        }
        $required = $app === null ? $this->env->keys($body) : array_column($app['env'], 'name');
        $clean = [];
        foreach ($required as $name) {
            if (preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $name) !== 1) {
                throw ValidationException::withMessages(['env' => 'نام متغیر env مجاز نیست.']);
            }
            $value = $values[$name] ?? '';
            $secret = preg_match('/PASS|SECRET|TOKEN|KEY/', $name) === 1;
            if ($value === '' || strlen($value) > 512 || ($secret && strlen($value) < 12)) {
                throw ValidationException::withMessages(['env' => 'مقدار «'.$name.'» کامل نیست.']);
            }
            $clean[$name] = $value;
        }

        return DB::transaction(function () use ($workspace, $host, $actor, $slug, $app, $body, $clean): OperationJob {
            $refs = [];
            foreach ($clean as $name => $value) {
                $credential = $this->credentials->store($workspace, $actor, 'docker_env', $value, $slug.':'.$name);
                $refs[] = ['name' => $name, 'credential_uuid' => $credential->uuid];
            }
            $job = app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $actor->id,
                type: 'docker.stack.apply',
                idempotencyKey: 'docker-stack:'.$host->uuid.':'.$slug.':'.Str::uuid()->toString(),
                plan: [
                    'contract_version' => '1',
                    'project_slug' => $slug,
                    'layout' => 'compose_only',
                    'compose_file' => 'compose.yml',
                    'template' => $app['id'] ?? 'custom',
                    'compose' => $body,
                    'env_refs' => $refs,
                    'approval' => true,
                    'host_executed' => false,
                ],
                mutatesHost: true,
                resourceType: 'local_host',
                resourceId: $host->id,
            ));
            app(AuditRecorder::class)->record($workspace, $actor, 'docker.stack_requested', 'local_host', $host->uuid, AuditResult::Success, [
                'template' => $app['id'] ?? 'custom',
                'env_names' => array_keys($clean),
                'host_executed' => false,
            ], operationJob: $job);

            return $job;
        });
    }
}
