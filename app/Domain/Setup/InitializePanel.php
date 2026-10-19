<?php

declare(strict_types=1);

namespace App\Domain\Setup;

use App\Domain\Catalog\SoftwareCapability;
use App\Domain\Settings\PanelListener;
use App\Domain\Setup\Enums\SetupState;
use App\Models\LocalHost;
use App\Models\SetupSession;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class InitializePanel
{
    public function save(Workspace $workspace, User $actor, string $name, array $packages): SetupSession
    {
        abort_unless(app(PanelListener::class)->owner($actor, $workspace), 403);
        abort_if($workspace->setupIsComplete(), 409);
        $packages = app(SoftwareCapability::class)->validateSetup($packages);

        return DB::transaction(function () use ($workspace, $actor, $name, $packages): SetupSession {
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $session = SetupSession::query()->forWorkspace($workspace->id)->where('state', SetupState::InProgress)->lockForUpdate()->first();
            abort_if($session?->operationJob !== null && ! $session->operationJob->status->isTerminal(), 409);
            $session ??= new SetupSession(['workspace_id' => $workspace->id, 'owner_id' => $actor->id, 'mode' => 'manual', 'state' => SetupState::InProgress, 'started_at' => now()]);
            $session->forceFill(['answers' => ['display_name' => $name, 'packages' => $packages], 'current_step' => 7])->save();

            return $session;
        });
    }

    public function apply(Workspace $workspace, User $actor, string $name, array $packages, string $key): SetupSession
    {
        return DB::transaction(function () use ($workspace, $actor, $name, $packages, $key): SetupSession {
            abort_unless(app(PanelListener::class)->owner($actor, $workspace), 403);
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $existing = SetupSession::query()->forWorkspace($workspace->id)->whereHas('operationJob', fn ($q) => $q->where('idempotency_key', $key))->first();
            if ($existing !== null) {
                abort_unless($existing->answers === ['display_name' => $name, 'packages' => app(SoftwareCapability::class)->validateSetup($packages)], 409);

                return $existing;
            }
            $session = $this->save($workspace, $actor, $name, $packages);
            $host = LocalHost::query()->forWorkspace($workspace->id)->where('slug', 'local-host')->firstOrFail();
            $job = app(SoftwareCapability::class)->request($workspace, $host, $actor, $session->answers['packages'], $key, 'setup.initialize', ['setup_uuid' => $session->uuid]);
            $session->forceFill(['operation_job_id' => $job->id, 'current_step' => 8, 'plan_hash' => $job->request_hash])->save();
            $session->components()->delete();
            foreach (['control-plane', ...$session->answers['packages']] as $index => $package) {
                $session->components()->create(['catalog_key' => $package, 'required' => $index === 0, 'selected' => true, 'sort_order' => $index, 'state' => 'ready', 'operation_job_step_id' => $job->steps()->firstOrFail()->id]);
            }

            return $session;
        });
    }
}
