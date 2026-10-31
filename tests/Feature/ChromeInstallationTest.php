<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Catalog\ChromeInstallation;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Enums\StepStatus;
use App\Domain\Jobs\OperationRunner;
use App\Models\LocalHost;
use App\Models\OperationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class ChromeInstallationTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    public function test_request_is_authorized_scoped_deduplicated_and_fails_closed_without_a_path(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'local-host']);
        $url = route('catalog.chrome.install', $host);
        $this->actingAs($owner)->post($url, ['executable_path' => '/tmp/fake', 'command' => 'ignored'])->assertRedirect(route('catalog.index'));
        $this->post($url)->assertRedirect();
        $this->assertDatabaseCount('operation_jobs', 1);
        $this->assertDatabaseCount('operation_job_outbox', 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'chrome.install_requested']);
        $job = OperationJob::query()->sole();
        $this->assertSame('google-chrome', $job->plan['input']['package']);
        $this->assertSame('latest_stable', $job->plan['input']['channel']);
        $this->assertSame('mohammadhejazirad/install-google-chrome-server', $job->plan['input']['repo']);
        $this->assertArrayNotHasKey('version', $job->plan['input']);
        $this->assertArrayNotHasKey('sha256', $job->plan['input']);
        $this->assertArrayNotHasKey('command', $job->plan['input']);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame(JobStatus::Failed, $job->fresh()->status);
        $this->get(route('catalog.index'))->assertInertia(fn (Assert $p) => $p
            ->where('chrome.executable_path', null)->where('chrome.execution_available', false)
            ->where('chrome.job.error_code', 'EXECUTION_DISABLED'));
        $this->post($url)->assertRedirect();
        $this->assertDatabaseCount('operation_jobs', 2);

        [$foreign] = $this->panelAccount(setupComplete: true);
        $this->flushSession();
        $this->actingAs($foreign)->post($url)->assertNotFound();
        $this->get(route('catalog.index'))->assertInertia(fn (Assert $p) => $p->where('chrome.job', null));
    }

    public function test_operator_cannot_request_host_software_installation(): void
    {
        [$operator, $workspace] = $this->panelAccount(role: MembershipRole::Operator, setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'local-host']);
        $this->actingAs($operator)->post(route('catalog.chrome.install', $host))->assertForbidden();
        $this->assertDatabaseCount('operation_jobs', 0);
    }

    public function test_path_requires_successful_render_verification_and_cannot_come_from_plan(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'local-host']);
        $service = app(ChromeInstallation::class);
        $job = $service->request($workspace, $host, $owner, null, null);
        $job->forceFill(['status' => JobStatus::Succeeded, 'finished_at' => now()])->save();
        $this->assertNull($service->verifiedPath($job->fresh()));
        // Stored worker evidence fixture; this is not an actual browser installation.
        $path = '/opt/rahandaz/browsers/chrome/1.2.3.4/chrome-linux64/chrome';
        $step = $job->steps()->create(['key' => 'browser.verify', 'status' => StepStatus::Succeeded,
            'timeout_seconds' => 120, 'attempt' => 1, 'finished_at' => now(),
            'checkpoint' => ['schema_version' => '1', 'package' => 'google-chrome', 'version' => '1.2.3.4',
                'sha256' => hash('sha256', 'fixture'), 'render_verified' => true, 'executable_path' => $path]]);
        $this->actingAs($owner)->get(route('catalog.index'))->assertInertia(fn (Assert $p) => $p->where('chrome.executable_path', $path));
        $this->post(route('catalog.chrome.install', $host))->assertRedirect();
        $this->assertDatabaseCount('operation_jobs', 2);
        $this->get(route('catalog.index'))->assertInertia(fn (Assert $p) => $p->where('chrome.executable_path', $path));
        $step->forceFill(['checkpoint' => [...$step->checkpoint, 'executable_path' => '/tmp/fake']])->save();
        $this->assertNull($service->verifiedPath($job->fresh()));
        $step->forceFill(['checkpoint' => [...$step->checkpoint, 'executable_path' => $path, 'render_verified' => false]])->save();
        $this->assertNull($service->verifiedPath($job->fresh()));
    }
}
