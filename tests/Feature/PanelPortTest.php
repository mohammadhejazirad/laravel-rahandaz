<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Jobs\OperationRunner;
use App\Infrastructure\Executor\ExecutionGate;
use App\Models\LocalHost;
use App\Models\Membership;
use App\Models\OperationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class PanelPortTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = tempnam(sys_get_temp_dir(), 'panel-listener-');
        file_put_contents($this->state, json_encode(['schema_version' => '1', 'enabled' => true, 'allow_public_http' => true, 'host' => '192.0.2.1', 'port' => 8080, 'previous_port' => null]));
        config(['rahandaz.panel_listener_file' => $this->state]);
        Process::fake();
    }

    protected function tearDown(): void
    {
        @unlink($this->state);
        parent::tearDown();
    }

    private function owner(): array
    {
        [$user, $workspace] = $this->panelAccount(setupComplete: true);
        LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'local-host']);
        $this->actingAs($user);

        return [$user, $workspace];
    }

    private function submit(): OperationJob
    {
        $this->post(route('settings.panel-port'), ['port' => 8081, 'expected_port' => 8080])->assertRedirect()->assertSessionHasNoErrors();

        return OperationJob::query()->where('type', 'panel.port.update')->sole();
    }

    public function test_request_records_audited_job_without_running_a_process_in_http(): void
    {
        $this->owner();
        $job = $this->submit();
        $this->assertSame('queued', $job->status->value);
        $this->assertSame(8081, $job->plan['input']['port']);
        $this->assertDatabaseHas('audit_events', ['action' => 'panel.port.requested']);
        Process::assertNothingRan();
        $this->post(route('settings.panel-port'), ['port' => 8082, 'expected_port' => 8080])->assertSessionHasErrors('port');
        $this->assertSame(1, OperationJob::query()->count());
    }

    public function test_fresh_owner_can_manage_panel_port_before_host_setup_is_complete(): void
    {
        [$user, $workspace] = $this->panelAccount(setupComplete: false);
        $this->actingAs($user);
        $this->get(route('settings.profile'))->assertOk();
        $job = $this->submit();
        $host = LocalHost::query()->forWorkspace($workspace->id)->sole();
        $this->assertSame('local-host', $host->slug);
        $this->assertSame('pending_probe', $host->state->value);
        $this->assertSame('untested', $host->compatibility->value);
        $this->assertTrue($host->protected);
        $this->assertSame((string) $host->id, (string) $job->resource_id);
        $this->getJson(route('settings.panel-port.status', ['job' => $job->uuid]))->assertOk();
        $this->get(route('dashboard'))->assertRedirect(route('setup.show'));
        Process::assertNothingRan();
    }

    public function test_only_owner_can_change_port_and_worker_rechecks_membership(): void
    {
        [$user, $workspace] = $this->owner();
        $job = $this->submit();
        Membership::query()->where('workspace_id', $workspace->id)->where('user_id', $user->id)->update(['role' => MembershipRole::Admin->value]);
        $this->post(route('settings.panel-port'), ['port' => 8082, 'expected_port' => 8080])->assertForbidden();
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('AUTHORIZATION_REVOKED', $job->fresh()->error_code);
        Process::assertNothingRan();
    }

    public function test_invalid_reserved_browser_unsafe_and_stale_ports_are_rejected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->owner();
        foreach ([22, 443, 1719, 1720, 1723, 6000, 6667, 65536, '8081; reboot'] as $port) {
            $this->post(route('settings.panel-port'), ['port' => $port, 'expected_port' => 8080])->assertSessionHasErrors('port');
        }
        $this->post(route('settings.panel-port'), ['port' => 8081, 'expected_port' => 9000])->assertSessionHasErrors('port');
        $this->assertDatabaseCount('operation_jobs', 0);
        Process::assertNothingRan();
    }

    public function test_worker_uses_only_fixed_helper_and_requires_verified_result(): void
    {
        $this->owner();
        $job = $this->submit();
        Process::fake(['*' => Process::result(output: json_encode(['schema_version' => '1', 'status' => 'succeeded', 'port' => 8081, 'verified' => true]))]);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('succeeded', $job->fresh()->status->value);
        $this->assertTrue($job->steps()->first()->checkpoint['verified']);
        Process::assertRan(fn ($process) => $process->command === ['/usr/bin/python3', '-I', '/var/lib/rahandaz/current/infrastructure/operations/panel/client.py'] && str_contains($process->input, $job->uuid));
        $this->assertDatabaseHas('audit_events', ['action' => 'panel.port.applied']);
        app(OperationRunner::class)->run($job->id);
        Process::assertRanTimes(fn () => true, 1);
        $this->assertFalse(app(ExecutionGate::class)->allowsHostMutation());
    }

    public function test_helper_exit_zero_without_health_evidence_is_failure(): void
    {
        $this->owner();
        $job = $this->submit();
        Process::fake(['*' => Process::result(output: '{"status":"succeeded"}')]);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('failed', $job->fresh()->status->value);
        $this->assertSame('PANEL_PORT_FAILED', $job->fresh()->error_code);
    }

    public function test_missing_enrollment_keeps_host_execution_closed(): void
    {
        $this->owner();
        $job = $this->submit();
        unlink($this->state);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('PANEL_LISTENER_DISABLED', $job->fresh()->error_code);
        Process::assertNothingRan();
    }

    public function test_authenticated_status_allows_only_the_previous_configured_origin(): void
    {
        $this->owner();
        $job = $this->submit();
        $state = json_decode(file_get_contents($this->state), true);
        $state['port'] = 8081;
        $state['previous_port'] = 8080;
        file_put_contents($this->state, json_encode($state));
        $url = route('settings.panel-port.status', ['job' => $job->uuid]);
        $this->withHeader('Origin', 'http://192.0.2.1:8080')->getJson($url)->assertOk()->assertHeader('Access-Control-Allow-Origin', 'http://192.0.2.1:8080')->assertHeader('Access-Control-Allow-Credentials', 'true');
        $this->withHeader('Origin', 'http://evil.example:8080')->getJson($url)->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
        $state['previous_port'] = 80;
        file_put_contents($this->state, json_encode($state));
        $this->withHeader('Origin', 'http://192.0.2.1')->getJson($url)->assertOk()->assertHeader('Access-Control-Allow-Origin', 'http://192.0.2.1');
    }

    public function test_another_workspace_cannot_read_the_job_status(): void
    {
        $this->owner();
        $job = $this->submit();
        [$other] = $this->panelAccount(setupComplete: true);
        $this->flushSession();
        $this->actingAs($other)->getJson(route('settings.panel-port.status', ['job' => $job->uuid]))->assertNotFound();
    }

    public function test_production_csp_allows_polling_only_the_root_enrolled_panel_ip(): void
    {
        $this->app['env'] = 'production';
        $policy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("connect-src 'self' http://192.0.2.1:*", $policy);
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        unlink($this->state);
        $this->assertStringNotContainsString('connect-src', (string) $this->get(route('login'))->headers->get('Content-Security-Policy'));
    }
}
