<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Catalog\SoftwareCapability;
use App\Domain\Jobs\OperationRunner;
use App\Infrastructure\Executor\ExecutionGate;
use App\Models\LocalHost;
use App\Models\Membership;
use App\Models\OperationJob;
use App\Models\PanelNotification;
use App\Models\SetupSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class SoftwareSetupTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->partialMock(SoftwareCapability::class, fn ($mock) => $mock->shouldReceive('available')->andReturn(true));
        Process::fake();
    }

    private function account(bool $complete = false, MembershipRole $role = MembershipRole::Owner): array
    {
        [$user, $workspace] = $this->panelAccount($role, setupComplete: $complete);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'local-host']);
        $this->actingAs($user);

        return [$user, $workspace, $host];
    }

    private function apply(array $packages = []): OperationJob
    {
        $this->post(route('setup.apply'), ['display_name' => 'My server', 'packages' => $packages, 'idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();

        return OperationJob::query()->latest('id')->firstOrFail();
    }

    private function evidence(OperationJob $job): array
    {
        return ['schema_version' => '1', 'status' => 'succeeded', 'execution_id' => $job->uuid, 'operation' => $job->type, 'verified_at' => now()->timestamp, 'verified' => true, 'control_plane_verified' => true, 'os' => '24.04',
            'packages' => array_map(fn ($key) => ['package' => $key, 'version' => '1.2.3-1ubuntu1', 'verified' => true, 'changed' => true], $job->plan['input']['packages'])];
    }

    public function test_answers_resume_and_http_only_queues_then_verified_health_unlocks_dashboard(): void
    {
        [, $workspace, $host] = $this->account();
        $this->patch(route('setup.save'), ['display_name' => 'My server', 'packages' => ['jq']])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('setup.show'))->assertInertia(fn (Assert $page) => $page->where('session.answers.packages', ['jq'])->where('session.answers.display_name', 'My server'));
        $job = $this->apply(['jq']);
        Process::assertNothingRan();
        $this->assertNull($workspace->fresh()->setup_completed_at);
        $this->get(route('dashboard'))->assertRedirect(route('setup.show'));
        $this->assertDatabaseHas('setup_components', ['catalog_key' => 'control-plane', 'required' => true, 'state' => 'ready']);
        Process::fake(['*' => Process::result(output: json_encode($this->evidence($job)))]);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('succeeded', $job->fresh()->status->value);
        $this->assertNotNull($workspace->fresh()->setup_completed_at);
        $this->assertSame('My server', $host->fresh()->display_name);
        $this->assertSame('untested', $host->fresh()->compatibility->value);
        $this->assertSame('completed', SetupSession::query()->sole()->state->value);
        $this->assertDatabaseHas('audit_events', ['action' => 'software.install.verified']);
        $this->actingAs($job->actor->fresh())->get(route('dashboard'))->assertOk();
        app(OperationRunner::class)->run($job->id);
        Process::assertRanTimes(fn () => true, 1);
        $this->assertFalse(app(ExecutionGate::class)->allowsHostMutation());
    }

    public function test_setup_can_skip_all_optional_tools_but_not_health_verification(): void
    {
        [, $workspace] = $this->account();
        $job = $this->apply();
        $output = $this->evidence($job);
        $output['control_plane_verified'] = false;
        Process::fake(['*' => Process::result(output: json_encode($output))]);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('failed', $job->fresh()->status->value);
        $this->assertNull($workspace->fresh()->setup_completed_at);
        $this->assertSame('blocked', SetupSession::query()->sole()->state->value);
        $retry = $this->apply();
        Process::fake(['*' => Process::result(output: json_encode($this->evidence($retry)))]);
        app(OperationRunner::class)->run($retry->id);
        $this->assertNotNull($workspace->fresh()->setup_completed_at);
        $this->assertDatabaseCount('setup_sessions', 2);
    }

    public function test_idempotent_apply_and_running_resume_do_not_duplicate_install(): void
    {
        $this->account();
        $data = ['display_name' => 'My server', 'packages' => ['jq'], 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('setup.apply'), $data)->assertSessionHasNoErrors();
        $this->post(route('setup.apply'), $data)->assertSessionHasNoErrors();
        $job = OperationJob::query()->sole();
        Process::fake(['*' => Process::result(output: '{"schema_version":"1","status":"running"}')]);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('running', $job->fresh()->status->value);
        $this->assertDatabaseCount('setup_sessions', 1);
        $this->assertDatabaseCount('operation_job_outbox', 2);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame(1, $job->fresh()->attempt);
        $this->assertDatabaseCount('operation_job_outbox', 2);
        $this->patch(route('setup.save'), ['display_name' => 'changed', 'packages' => []])->assertStatus(409);
    }

    public function test_non_owner_cannot_initialize_and_worker_rechecks_ownership(): void
    {
        [$user, $workspace] = $this->account();
        $job = $this->apply();
        Membership::query()->where('user_id', $user->id)->where('workspace_id', $workspace->id)->update(['role' => 'admin']);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('AUTHORIZATION_REVOKED', $job->fresh()->error_code);
        $this->post(route('setup.apply'), ['display_name' => 'server', 'packages' => [], 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        Process::assertNothingRan();
    }

    public function test_software_install_rejects_unknown_packages_and_blocks_concurrent_requests(): void
    {
        $this->account(true);
        $url = route('catalog.software.install');
        foreach ([['bash'], ['jq;id'], ['jq', 'jq']] as $packages) {
            $this->post($url, ['packages' => $packages, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors();
        }
        $data = ['packages' => ['jq'], 'idempotency_key' => (string) Str::uuid()];
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, ['packages' => ['htop'], 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('packages');
        $this->assertDatabaseCount('operation_jobs', 1);
        Process::assertNothingRan();
    }

    public function test_false_or_cross_execution_evidence_is_rejected(): void
    {
        $this->account();
        $job = $this->apply(['jq']);
        $evidence = $this->evidence($job);
        $evidence['execution_id'] = (string) Str::uuid();
        Process::fake(['*' => Process::result(output: json_encode($evidence))]);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('failed', $job->fresh()->status->value);
        $this->assertFalse($job->steps()->first()->checkpoint['packages'][0]['verified']);
        $this->assertSame('skipped', $job->steps()->first()->checkpoint['packages'][0]['status']);
    }

    public function test_revoked_software_actor_and_cancelled_job_never_call_helper(): void
    {
        [$user, $workspace] = $this->account(true);
        $this->post(route('catalog.software.install'), ['packages' => ['jq'], 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $job = OperationJob::query()->sole();
        Membership::query()->where('user_id', $user->id)->where('workspace_id', $workspace->id)->update(['role' => 'viewer']);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('AUTHORIZATION_REVOKED', $job->fresh()->error_code);
        Process::assertNothingRan();
    }

    public function test_partial_batch_has_individual_counts_logs_and_one_terminal_notification(): void
    {
        [, $workspace] = $this->account(true);
        $this->post(route('catalog.software.install'), ['packages' => ['ffmpeg', 'jq'], 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $job = OperationJob::query()->sole();
        $output = $this->evidence($job);
        $output['status'] = 'running';
        $output['verified'] = false;
        $output['packages'][0] = ['package' => 'ffmpeg', 'version' => null, 'verified' => false, 'changed' => false, 'status' => 'running', 'phase' => 'download'];
        $output['packages'][1] = ['package' => 'jq', 'version' => null, 'verified' => false, 'changed' => false, 'status' => 'queued'];
        $output['events'] = [['sequence' => 1, 'package' => 'ffmpeg', 'phase' => 'download']];
        Process::fake(['*' => Process::result(output: json_encode($output))]);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('running', $job->fresh()->status->value);
        $this->assertDatabaseMissing('panel_notifications', ['event_key' => 'operation:'.$job->uuid.':terminal']);
        $logCount = $job->logs()->count();
        app(OperationRunner::class)->run($job->id);
        $this->assertSame($logCount, $job->logs()->count());
        $output['status'] = 'failed';
        $output['error_code'] = 'PACKAGE_DOWNLOAD_FAILED';
        $output['packages'][0]['status'] = 'failed';
        $output['packages'][0]['error_code'] = 'PACKAGE_DOWNLOAD_FAILED';
        $output['packages'][1] = ['package' => 'jq', 'status' => 'succeeded', 'version' => '1.7', 'verified' => true, 'changed' => false, 'executable_path' => '/usr/bin/jq'];
        Process::fake(['*' => Process::result(output: json_encode($output))]);
        app(OperationRunner::class)->run($job->id);
        $payload = app(SoftwareCapability::class)->payload($job->fresh());
        $this->assertSame(['total' => 2, 'succeeded' => 1, 'failed' => 1, 'skipped' => 0], $payload['summary']);
        $this->assertNotEmpty($payload['packages'][0]['message']);
        $this->assertSame('succeeded', $payload['packages'][1]['status']);
        $this->assertSame(1, PanelNotification::query()->where('event_key', 'operation:'.$job->uuid.':terminal')->count());
        app(OperationRunner::class)->run($job->id);
        $this->assertSame(1, PanelNotification::query()->where('event_key', 'operation:'.$job->uuid.':terminal')->count());
        $this->assertSame('/usr/bin/jq', app(SoftwareCapability::class)->installed($workspace)[0]['executable_path']);
        $this->assertFalse(app(ExecutionGate::class)->allowsHostMutation());
    }

    public function test_setup_cannot_install_runtimes_or_chrome_and_catalog_groups_every_entry(): void
    {
        $this->account();
        $this->post(route('setup.apply'), ['display_name' => 'My server', 'packages' => ['google-chrome'], 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('packages');
        $this->assertDatabaseCount('operation_jobs', 0);
        $capability = app(SoftwareCapability::class);
        foreach ($capability->catalog() as $entry) {
            $this->assertArrayHasKey($entry['category'], $capability->categories());
        }
        $this->assertCount(4, $capability->setupCatalog());
    }
}
