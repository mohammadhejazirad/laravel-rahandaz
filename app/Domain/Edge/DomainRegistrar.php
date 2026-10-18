<?php

declare(strict_types=1);

namespace App\Domain\Edge;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Edge\Exceptions\DomainRejected;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Domain\Projects\Enums\CertificateInstallState;
use App\Domain\Projects\Enums\RoutingKind;
use App\Domain\Projects\Enums\TlsMode;
use App\Models\Certificate;
use App\Models\Domain;
use App\Models\Environment;
use App\Models\NginxSite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DomainRegistrar
{
    public function __construct(
        private readonly RecordOperation $operations,
        private readonly AuditRecorder $audit,
    ) {}

    public function register(
        Environment $environment,
        User $actor,
        string $hostname,
        ?string $requestId,
        ?string $sourceIp,
    ): Domain {
        $hostname = strtolower(trim($hostname));
        $environment->loadMissing('project.workspace', 'localHost');
        Gate::forUser($actor)->authorize('deploy', $environment->project);

        if (preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $hostname) !== 1
            || strlen($hostname) > 253
            || filter_var($hostname, FILTER_VALIDATE_IP) !== false
            || preg_match('/\.(?:local|internal|test|example)$/D', $hostname) === 1
            || str_ends_with($hostname, '.invalid')
            || str_ends_with($hostname, '.localhost')
            || $hostname === 'localhost') {
            throw new DomainRejected('نام دامنه عمومی معتبر نیست.');
        }

        return DB::transaction(function () use ($environment, $actor, $hostname, $requestId, $sourceIp): Domain {
            Environment::query()->lockForUpdate()->findOrFail($environment->id);
            DB::select('select pg_advisory_xact_lock(hashtext(?)::bigint)', [
                'domain:'.$environment->local_host_id.':'.$hostname,
            ]);

            $duplicate = Domain::query()
                ->where('local_host_id', $environment->local_host_id)
                ->where('hostname', $hostname)
                ->exists();

            if ($duplicate) {
                throw new DomainRejected('این نام دامنه روی همین میزبان ثبت شده است.');
            }

            $panelName = NginxSite::query()
                ->where('local_host_id', $environment->local_host_id)
                ->where('site_role', 'panel')
                ->where('server_name', $hostname)
                ->exists();

            if ($panelName) {
                throw new DomainRejected('این نام، مسیر پنل راه‌انداز است و به پروژه وصل نمی‌شود.');
            }

            $existingSite = NginxSite::query()
                ->where('environment_id', $environment->id)
                ->lockForUpdate()
                ->first();

            if ($existingSite instanceof NginxSite) {
                throw new DomainRejected('این محیط از قبل یک سایت Nginx دارد. در این نسخه هر محیط یک دامنه عمومی می‌گیرد.');
            }

            $domain = Domain::query()->create([
                'workspace_id' => $environment->workspace_id,
                'environment_id' => $environment->id,
                'local_host_id' => $environment->local_host_id,
                'hostname' => $hostname,
                'routing_kind' => RoutingKind::PublicHostname,
                'temporary_path' => null,
                'tls_mode' => TlsMode::Off,
                'certificate_id' => null,
                'validation_status' => 'unset',
            ]);

            $site = NginxSite::query()->create([
                'workspace_id' => $environment->workspace_id,
                'local_host_id' => $environment->local_host_id,
                'environment_id' => $environment->id,
                'site_role' => 'project',
                'server_name' => $hostname,
                'certificate_id' => null,
                'upload_limit_mb' => 16,
                'gzip' => true,
                'websocket' => false,
                'access_log' => true,
                'force_https' => false,
                'config_revision' => 1,
                'protected' => false,
            ]);

            $operation = $this->operations->record(new RecordOperationData(
                workspaceId: $environment->workspace_id,
                actorId: $actor->id,
                type: 'nginx.site.apply',
                idempotencyKey: 'domain:'.$domain->uuid,
                plan: [
                    'domain_uuid' => $domain->uuid,
                    'site_uuid' => $site->uuid,
                    'hostname' => $hostname,
                    'environment_uuid' => $environment->uuid,
                    'host_executed' => false,
                ],
                mutatesHost: true,
                resourceType: 'environment',
                resourceId: $environment->id,
            ));

            $this->audit->record(
                $environment->project?->workspace,
                $actor,
                'domain.create',
                'domain',
                $domain->uuid,
                AuditResult::Success,
                [
                    'hostname' => $hostname,
                    'environment_uuid' => $environment->uuid,
                    'nginx_site_uuid' => $site->uuid,
                    'operation_job_uuid' => $operation->uuid,
                    'host_executed' => false,
                    'dns_validated' => false,
                ],
                $requestId,
                $sourceIp,
                $operation,
            );

            return $domain;
        });
    }

    public function requestCertificate(
        Domain $domain,
        User $actor,
        ?string $requestId,
        ?string $sourceIp,
    ): Certificate {
        $domain->loadMissing('environment.project.workspace');
        Gate::forUser($actor)->authorize('deploy', $domain->environment->project);

        if ($domain->tls_mode !== TlsMode::Off || $domain->certificate_id !== null) {
            throw new DomainRejected('برای این دامنه از قبل حالت TLS ثبت شده است.');
        }

        return DB::transaction(function () use ($domain, $actor, $requestId, $sourceIp): Certificate {
            $locked = Domain::query()->lockForUpdate()->findOrFail($domain->id);
            $locked->loadMissing('environment.project.workspace');

            if ($locked->certificate_id !== null) {
                throw new DomainRejected('برای این دامنه از قبل گواهی ثبت شده است.');
            }

            $certificate = Certificate::query()->create([
                'workspace_id' => $locked->workspace_id,
                'local_host_id' => $locked->local_host_id,
                'mode' => 'acme',
                'algorithm' => 'ecdsa_secp384r1',
                'install_state' => CertificateInstallState::Requested,
                'issuer' => null,
                'not_before' => null,
                'not_after' => null,
                'fingerprint' => null,
                'credential_id' => null,
                'renewal_status' => 'none',
            ]);

            $locked->forceFill([
                'tls_mode' => TlsMode::Acme,
                'certificate_id' => $certificate->id,
                'validation_status' => 'pending',
            ])->save();

            NginxSite::query()
                ->where('environment_id', $locked->environment_id)
                ->where('site_role', 'project')
                ->update([
                    'certificate_id' => null,
                    'force_https' => false,
                ]);

            $operation = $this->operations->record(new RecordOperationData(
                workspaceId: $locked->workspace_id,
                actorId: $actor->id,
                type: 'certificate.issue',
                idempotencyKey: 'certificate:'.$certificate->uuid,
                plan: [
                    'domain_uuid' => $locked->uuid,
                    'certificate_uuid' => $certificate->uuid,
                    'hostname' => $locked->hostname,
                    'mode' => 'acme',
                    'validation' => 'pending',
                    'host_executed' => false,
                ],
                mutatesHost: true,
                resourceType: 'environment',
                resourceId: $locked->environment_id,
            ));

            $this->audit->record(
                $locked->environment?->project?->workspace,
                $actor,
                'domain.certificate_request',
                'certificate',
                $certificate->uuid,
                AuditResult::Success,
                [
                    'domain_uuid' => $locked->uuid,
                    'hostname' => $locked->hostname,
                    'install_state' => CertificateInstallState::Requested->value,
                    'operation_job_uuid' => $operation->uuid,
                    'host_executed' => false,
                    'public_ca' => false,
                ],
                $requestId,
                $sourceIp,
                $operation,
            );

            return $certificate;
        });
    }

    public function remove(Domain $domain, User $actor, ?string $requestId, ?string $sourceIp): void
    {
        $domain->loadMissing('certificate', 'environment.project.workspace');
        Gate::forUser($actor)->authorize('deploy', $domain->environment->project);
        $certificate = $domain->certificate;

        if ($certificate instanceof Certificate && $certificate->install_state === CertificateInstallState::Installed) {
            throw new DomainRejected('گواهی نصب‌شده را بدون عملیات میزبان و بررسی وابستگی حذف نمی‌کنیم.');
        }

        DB::transaction(function () use ($domain, $actor, $requestId, $sourceIp): void {
            $locked = Domain::query()->lockForUpdate()->findOrFail($domain->id);
            $locked->loadMissing('environment.project.workspace');
            $hostname = $locked->hostname;
            $uuid = $locked->uuid;
            $environmentId = $locked->environment_id;
            $workspace = $locked->environment?->project?->workspace;

            $operation = $this->operations->record(new RecordOperationData(
                workspaceId: $locked->workspace_id,
                actorId: $actor->id,
                type: 'nginx.site.remove',
                idempotencyKey: 'domain-remove:'.$locked->uuid,
                plan: [
                    'domain_uuid' => $locked->uuid,
                    'hostname' => $hostname,
                    'host_executed' => false,
                ],
                mutatesHost: true,
                resourceType: 'environment',
                resourceId: $environmentId,
            ));

            $locked->delete();

            $remaining = Domain::query()->where('environment_id', $environmentId)->exists();

            if (! $remaining) {
                NginxSite::query()
                    ->where('environment_id', $environmentId)
                    ->where('site_role', 'project')
                    ->where('protected', false)
                    ->delete();
            }

            $this->audit->record(
                $workspace,
                $actor,
                'domain.delete',
                'domain',
                $uuid,
                AuditResult::Success,
                [
                    'hostname' => $hostname,
                    'operation_job_uuid' => $operation->uuid,
                    'host_executed' => false,
                ],
                $requestId,
                $sourceIp,
                $operation,
            );
        });
    }
}
