<?php

declare(strict_types=1);

namespace App\Domain\Databases;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Databases\Enums\DatabaseEngine;
use App\Domain\Databases\Enums\DatabasePrivilege;
use App\Domain\Databases\Enums\ProjectDatabaseStatus;
use App\Domain\Databases\Exceptions\DatabaseRejected;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Infrastructure\Secrets\CredentialStore;
use App\Models\DatabaseInstance;
use App\Models\DatabaseUser;
use App\Models\DatabaseUserGrant;
use App\Models\Environment;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\ProjectDatabase;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class DatabaseRegistrar
{
    public function __construct(
        private readonly CredentialStore $credentials,
        private readonly RecordOperation $operations,
        private readonly AuditRecorder $audit,
    ) {}

    public function registerInstance(
        Workspace $workspace,
        LocalHost $host,
        User $actor,
        DatabaseEngine $engine,
        string $version,
        string $connectionMode,
        ?int $port,
        ?string $socketPath,
        ?string $requestId,
        ?string $sourceIp,
    ): DatabaseInstance {
        if ((string) $host->workspace_id !== (string) $workspace->id) {
            throw new DatabaseRejected('میزبان خارج از این فضای کاری است.');
        }

        if (! in_array($engine, [DatabaseEngine::Postgresql, DatabaseEngine::Mysql, DatabaseEngine::Mariadb, DatabaseEngine::Mongodb], true)) {
            throw new DatabaseRejected('این موتور در فرم نسخه اول نیست.');
        }

        if (preg_match('/^[0-9][A-Za-z0-9._-]{0,31}$/', $version) !== 1) {
            throw new DatabaseRejected('نسخه موتور معتبر نیست.');
        }

        $attributes = [
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'managed_service_id' => null,
            'engine' => $engine,
            'version' => $version,
            'bind_scope' => 'loopback',
            'ownership' => 'project_host',
            'observed_state' => 'unknown',
            'observed_at' => null,
        ];

        if ($connectionMode === 'tcp') {
            if ($port === null || $port < 1024 || $port > 65535) {
                throw new DatabaseRejected('پورت باید بین ۱۰۲۴ و ۶۵۵۳۵ باشد تا با سرویس‌های سیستم تداخل نکند.');
            }

            $attributes['connection_mode'] = 'tcp';
            $attributes['endpoint_address'] = '127.0.0.1';
            $attributes['port'] = $port;
            $attributes['socket_path'] = null;
        } elseif ($connectionMode === 'socket') {
            $socketPath = (string) $socketPath;

            if (preg_match('#^/(run|var/run|var/lib)/[A-Za-z0-9_./-]{1,160}$#', $socketPath) !== 1 || str_contains($socketPath, '..')) {
                throw new DatabaseRejected('مسیر سوکت مجاز نیست.');
            }

            $attributes['connection_mode'] = 'socket';
            $attributes['endpoint_address'] = null;
            $attributes['port'] = null;
            $attributes['socket_path'] = $socketPath;
        } else {
            throw new DatabaseRejected('حالت اتصال باید tcp یا socket باشد.');
        }

        return DB::transaction(function () use ($workspace, $host, $actor, $attributes, $requestId, $sourceIp): DatabaseInstance {
            $conflict = DatabaseInstance::query()
                ->where('local_host_id', $host->id)
                ->where(function ($query) use ($attributes): void {
                    if ($attributes['connection_mode'] === 'tcp') {
                        $query->where('port', $attributes['port']);
                    } else {
                        $query->where('socket_path', $attributes['socket_path']);
                    }
                })
                ->exists();

            if ($conflict) {
                throw new DatabaseRejected('این پورت یا سوکت روی میزبان قبلاً برای یک نمونه دیتابیس ثبت شده است.');
            }

            $instance = DatabaseInstance::query()->create($attributes);
            $operation = $this->operations->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $actor->id,
                type: 'database.instance',
                idempotencyKey: 'database-instance:'.$instance->uuid,
                plan: [
                    'instance_uuid' => $instance->uuid,
                    'engine' => $instance->engine->value,
                    'connection_mode' => $instance->connection_mode,
                    'port' => $instance->port,
                    'host_executed' => false,
                    'observed_state' => 'unknown',
                ],
                mutatesHost: true,
                resourceType: 'database_instance',
                resourceId: $instance->id,
            ));

            $this->audit->record(
                $workspace,
                $actor,
                'database.instance_register',
                'database_instance',
                $instance->uuid,
                AuditResult::Success,
                [
                    'engine' => $instance->engine->value,
                    'connection_mode' => $instance->connection_mode,
                    'port' => $instance->port,
                    'operation_job_uuid' => $operation->uuid,
                    'host_executed' => false,
                    'observed_state' => 'unknown',
                ],
                $requestId,
                $sourceIp,
                $operation,
            );

            return $instance;
        });
    }

    public function createDatabase(
        DatabaseInstance $instance,
        Environment $environment,
        User $actor,
        string $name,
        string $username,
        string $password,
        ?string $requestId,
        ?string $sourceIp,
    ): ProjectDatabase {
        if ($instance->protected || $instance->ownership !== 'project_host') {
            throw new DatabaseRejected('نمونه محافظت‌شده راه‌انداز برای ساخت دیتابیس پروژه قابل استفاده نیست.');
        }
        $this->assertNames($name, $username);
        $this->assertPassword($password);
        $instance->loadMissing('workspace');
        $environment->loadMissing('project.workspace');

        if ((string) $environment->workspace_id !== (string) $instance->workspace_id
            || (string) $environment->local_host_id !== (string) $instance->local_host_id) {
            throw new DatabaseRejected('محیط و نمونه دیتابیس باید روی یک میزبان و یک فضای کاری باشند.');
        }

        $workspace = $instance->workspace;

        if (! $workspace instanceof Workspace) {
            throw new DatabaseRejected('فضای کاری نمونه پیدا نشد.');
        }

        return DB::transaction(function () use ($instance, $environment, $actor, $name, $username, $password, $workspace, $requestId, $sourceIp): ProjectDatabase {
            $credential = $this->credentials->store($workspace, $actor, 'database_password', $password, $username);
            $user = DatabaseUser::query()->create([
                'database_instance_id' => $instance->id,
                'workspace_id' => $instance->workspace_id,
                'username' => $username,
                'credential_id' => $credential->id,
                'auth_method' => $this->authMethod($instance->engine),
                'status' => 'active',
            ]);
            $database = ProjectDatabase::query()->create([
                'database_instance_id' => $instance->id,
                'environment_id' => $environment->id,
                'workspace_id' => $instance->workspace_id,
                'name' => $name,
                'owner_database_user_id' => $user->id,
                'status' => ProjectDatabaseStatus::Requested,
            ]);
            DatabaseUserGrant::query()->create([
                'database_instance_id' => $instance->id,
                'database_user_id' => $user->id,
                'project_database_id' => $database->id,
                'privilege' => DatabasePrivilege::Owner,
            ]);
            $operation = $this->operations->record(new RecordOperationData(
                workspaceId: $instance->workspace_id,
                actorId: $actor->id,
                type: 'database.create',
                idempotencyKey: 'project-database:'.$database->uuid,
                plan: [
                    'database_uuid' => $database->uuid,
                    'instance_uuid' => $instance->uuid,
                    'environment_uuid' => $environment->uuid,
                    'name' => $name,
                    'username' => $username,
                    'status' => ProjectDatabaseStatus::Requested->value,
                    'host_executed' => false,
                ],
                mutatesHost: true,
                resourceType: 'project_database',
                resourceId: $database->id,
            ));

            $this->audit->record(
                $workspace,
                $actor,
                'database.create',
                'project_database',
                $database->uuid,
                AuditResult::Success,
                [
                    'name' => $name,
                    'username' => $username,
                    'instance_uuid' => $instance->uuid,
                    'environment_uuid' => $environment->uuid,
                    'operation_job_uuid' => $operation->uuid,
                    'host_executed' => false,
                    'status' => ProjectDatabaseStatus::Requested->value,
                ],
                $requestId,
                $sourceIp,
                $operation,
            );

            return $database;
        });
    }

    public function changePassword(
        DatabaseUser $databaseUser,
        User $actor,
        string $password,
        ?string $requestId,
        ?string $sourceIp,
    ): void {
        throw new DatabaseRejected('تغییر رمز تا تکمیل اعمال و بازیابی credential روی میزبان در دسترس نیست.');
    }

    private function stagePassword(
        DatabaseUser $databaseUser,
        User $actor,
        string $password,
        ?string $requestId,
        ?string $sourceIp,
    ): void {
        $this->assertPassword($password);
        $databaseUser->loadMissing('databaseInstance.workspace');
        $workspace = $databaseUser->databaseInstance?->workspace;

        if (! $workspace instanceof Workspace) {
            throw new DatabaseRejected('فضای کاری کاربر دیتابیس پیدا نشد.');
        }

        DB::transaction(function () use ($databaseUser, $actor, $password, $workspace, $requestId, $sourceIp): void {
            $credential = $this->credentials->store($workspace, $actor, 'database_password', $password, $databaseUser->username);
            $databaseUser->credential_id = $credential->id;
            $databaseUser->save();
            $operation = $this->operations->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $actor->id,
                type: 'database.password',
                idempotencyKey: 'database-user-pass:'.$databaseUser->uuid.':'.$credential->uuid,
                plan: [
                    'database_user_uuid' => $databaseUser->uuid,
                    'envelope_uuid' => $credential->uuid,
                    'host_executed' => false,
                ],
                mutatesHost: true,
                resourceType: 'database_user',
                resourceId: $databaseUser->id,
            ));
            $this->audit->record(
                $workspace,
                $actor,
                'database.password',
                'database_user',
                $databaseUser->uuid,
                AuditResult::Success,
                [
                    'username' => $databaseUser->username,
                    'envelope_uuid' => $credential->uuid,
                    'operation_job_uuid' => $operation->uuid,
                    'host_executed' => false,
                ],
                $requestId,
                $sourceIp,
                $operation,
            );
        });
    }

    public function forgetRequested(ProjectDatabase $database, User $actor, string $confirmation, ?string $requestId, ?string $sourceIp): void
    {
        if (! hash_equals($database->name, $confirmation)) {
            throw new DatabaseRejected('نام دیتابیس برای تأیید حذف درست نیست.');
        }

        if ($database->protected || $database->status !== ProjectDatabaseStatus::Requested) {
            throw new DatabaseRejected('فقط درخواست هنوز اجرا‌نشده را می‌توان از پنل حذف کرد. دیتابیس آماده یا محافظت‌شده روی میزبان drop نمی‌شود.');
        }

        $database->loadMissing('databaseInstance.workspace');
        $workspace = $database->databaseInstance?->workspace;

        DB::transaction(function () use ($database, $actor, $workspace, $requestId, $sourceIp): void {
            $locked = ProjectDatabase::query()->lockForUpdate()->findOrFail($database->id);

            if ($locked->protected || $locked->status !== ProjectDatabaseStatus::Requested) {
                throw new DatabaseRejected('وضعیت دیتابیس اجازه حذف رکورد را نمی‌دهد.');
            }

            $uuid = $locked->uuid;
            $name = $locked->name;
            DatabaseUserGrant::query()->where('project_database_id', $locked->id)->delete();
            $locked->delete();
            $this->audit->record(
                $workspace,
                $actor,
                'database.forget',
                'project_database',
                $uuid,
                AuditResult::Success,
                [
                    'name' => $name,
                    'host_executed' => false,
                    'dropped_on_host' => false,
                ],
                $requestId,
                $sourceIp,
            );
        });
    }

    public function queueConnectionTest(ProjectDatabase $database, User $actor, ?string $requestId, ?string $sourceIp): OperationJob
    {
        $database->loadMissing('databaseInstance.workspace', 'environment');
        $workspace = $database->databaseInstance?->workspace;

        if (! $workspace instanceof Workspace) {
            throw new DatabaseRejected('فضای کاری دیتابیس پیدا نشد.');
        }

        return DB::transaction(function () use ($database, $actor, $workspace, $requestId, $sourceIp): OperationJob {
            $operation = $this->operations->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $actor->id,
                type: 'database.ping',
                idempotencyKey: 'database-ping:'.$database->uuid.':'.$actor->id,
                plan: [
                    'database_uuid' => $database->uuid,
                    'instance_uuid' => $database->databaseInstance?->uuid,
                    'host_executed' => false,
                ],
                mutatesHost: false,
                resourceType: 'project_database',
                resourceId: $database->id,
            ));
            $this->audit->record(
                $workspace,
                $actor,
                'database.ping',
                'project_database',
                $database->uuid,
                AuditResult::Success,
                [
                    'operation_job_uuid' => $operation->uuid,
                    'host_executed' => false,
                    'connection_proven' => false,
                ],
                $requestId,
                $sourceIp,
                $operation,
            );

            return $operation;
        });
    }

    private function authMethod(DatabaseEngine $engine): string
    {
        return match ($engine) {
            DatabaseEngine::Postgresql => 'scram_sha256',
            DatabaseEngine::Mysql, DatabaseEngine::Mariadb => 'mysql_native',
            DatabaseEngine::Mongodb => 'mongodb_scram',
            default => throw new DatabaseRejected('روش احراز این موتور پشتیبانی نمی‌شود.'),
        };
    }

    private function assertNames(string $name, string $username): void
    {
        foreach (['name' => $name, 'username' => $username] as $label => $value) {
            if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $value) !== 1) {
                throw new DatabaseRejected($label === 'name' ? 'نام دیتابیس معتبر نیست.' : 'نام کاربر دیتابیس معتبر نیست.');
            }
        }

        $reserved = ['postgres', 'template0', 'template1', 'rahandaz', 'mysql', 'information_schema', 'performance_schema', 'sys', 'admin', 'local', 'root', 'sa'];

        if (in_array($name, $reserved, true) || in_array($username, ['postgres', 'root', 'rahandaz', 'mysql', 'admin', 'sa'], true)) {
            throw new DatabaseRejected('این نام برای دیتابیس راه‌انداز یا موتور رزرو شده است.');
        }
    }

    private function assertPassword(string $password): void
    {
        if (strlen($password) < 16 || strlen($password) > 128 || preg_match('/\s/', $password) === 1) {
            throw new DatabaseRejected('گذرواژه دیتابیس باید ۱۶ تا ۱۲۸ نویسه و بدون فاصله باشد.');
        }
    }
}
