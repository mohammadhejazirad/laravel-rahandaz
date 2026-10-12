<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Projects\Enums\DeploymentStatus;
use App\Domain\Projects\Enums\PhaseStatus;
use App\Domain\Projects\Enums\ProjectSourceType;
use App\Domain\Projects\Enums\RollbackStatus;
use App\Models\AuditEvent;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\Project;
use App\Models\ProjectSourceArchive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsPostgresConstraints;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;
use ZipArchive;

class ProjectArchiveUploadTest extends TestCase
{
    use AssertsPostgresConstraints, CreatesPanelAccount, RefreshDatabase;

    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('project_archives');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_owner_can_upload_a_private_zip_and_queue_manual_deployment_workflow(): void
    {
        [$owner, $project, $environment] = $this->projectGraph();
        $upload = $this->uploadedZip('release.zip', [
            'composer.json' => '{"name":"example/project"}',
            'public/index.php' => '<?php echo "ok";',
        ]);

        $this->actingAs($owner)
            ->post(route('projects.source-archives.store', [$project, $environment]), ['archive' => $upload])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHas('info');

        $archive = ProjectSourceArchive::query()->sole();
        $deployment = Deployment::query()->sole();
        $operation = OperationJob::query()->where('type', 'deploy.archive')->sole();

        Storage::disk('project_archives')->assertExists($archive->path);
        $this->assertSame('project_archives', $archive->disk);
        $this->assertSame('release.zip', $archive->original_name);
        $this->assertSame($project->id, $archive->project_id);
        $this->assertSame($owner->id, $archive->uploaded_by);
        $this->assertSame(ProjectSourceType::Archive, $deployment->source_type);
        $this->assertSame($archive->id, $deployment->source_archive_id);
        $this->assertNull($deployment->commit_sha);
        $this->assertNull($deployment->remote_repository_id);
        $this->assertSame($archive->sha256, $deployment->artifact_digest);
        $this->assertSame(DeploymentStatus::Queued, $deployment->status);
        $this->assertSame($operation->id, $deployment->operation_job_id);
        $this->assertSame($environment->id, $operation->resource_id);
        $this->assertSame('environment', $operation->resource_type);
        $this->assertTrue($operation->outboxMessages()->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'project.archive_upload')->where('resource_id', $archive->uuid)->exists());

        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('archive_upload.available', true)
            ->where('source_archives.0.uuid', $archive->uuid)
            ->where('source_archives.0.deployment.operation_job_uuid', $operation->uuid)
            ->missing('source_archives.0.path')
            ->missing('source_archives.0.id'));
    }

    public function test_duplicate_digest_reuses_private_archive_but_creates_a_new_deployment(): void
    {
        [$owner, $project, $environment] = $this->projectGraph();
        $entries = ['index.html' => '<h1>Rahandaz</h1>'];

        $this->actingAs($owner)->post(route('projects.source-archives.store', [$project, $environment]), [
            'archive' => $this->uploadedZip('first.zip', $entries),
        ])->assertRedirect();

        Deployment::query()->sole()->forceFill([
            'status' => DeploymentStatus::Failed,
            'failure_code' => 'EXECUTION_DISABLED',
        ])->save();

        $this->actingAs($owner)->post(route('projects.source-archives.store', [$project, $environment]), [
            'archive' => $this->uploadedZip('second.zip', $entries),
        ])->assertRedirect()->assertSessionHas('info', fn (string $message) => str_contains($message, 'قبلاً بررسی'));

        $this->assertSame(1, ProjectSourceArchive::query()->count());
        $this->assertSame(2, Deployment::query()->count());
        $this->assertSame(2, OperationJob::query()->where('type', 'deploy.archive')->count());
        $archive = ProjectSourceArchive::query()->sole();
        Storage::disk('project_archives')->assertExists($archive->path);
        $this->assertCount(1, Storage::disk('project_archives')->allFiles());
    }

    public function test_viewer_and_cross_project_environment_cannot_upload(): void
    {
        [$viewer, $project, $environment] = $this->projectGraph(MembershipRole::Viewer);

        $this->actingAs($viewer)->post(route('projects.source-archives.store', [$project, $environment]), [
            'archive' => $this->uploadedZip('viewer.zip', ['index.html' => 'viewer']),
        ])->assertForbidden();

        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'scope-host']);
        $first = Project::factory()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'created_by' => $owner->id]);
        $second = Project::factory()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'created_by' => $owner->id]);
        $foreignEnvironment = Environment::factory()->create(['project_id' => $second->id, 'workspace_id' => $workspace->id, 'local_host_id' => $host->id]);

        $this->flushSession();
        $this->actingAs($owner)->post(route('projects.source-archives.store', [$first, $foreignEnvironment]), [
            'archive' => $this->uploadedZip('scope.zip', ['index.html' => 'scope']),
        ])->assertForbidden();

        $this->assertSame(0, ProjectSourceArchive::query()->count());
    }

    public function test_spoofed_or_unsafe_zip_is_rejected_without_persisting_file(): void
    {
        [$owner, $project, $environment] = $this->projectGraph();
        $plainPath = tempnam(sys_get_temp_dir(), 'rahandaz-text-');
        $this->assertNotFalse($plainPath);
        $this->temporaryFiles[] = $plainPath;
        file_put_contents($plainPath, 'not a zip');

        $this->from(route('projects.show', $project))->actingAs($owner)
            ->post(route('projects.source-archives.store', [$project, $environment]), [
                'archive' => new UploadedFile($plainPath, 'spoofed.zip', 'application/zip', null, true),
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('archive');

        $this->from(route('projects.show', $project))->actingAs($owner)
            ->post(route('projects.source-archives.store', [$project, $environment]), [
                'archive' => $this->uploadedZip('unsafe.zip', ['../outside.php' => '<?php']),
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('archive');

        $this->assertSame(0, ProjectSourceArchive::query()->count());
        $this->assertSame([], Storage::disk('project_archives')->allFiles());
    }

    public function test_database_rejects_an_archive_from_another_project(): void
    {
        [$owner, $project, $environment] = $this->projectGraph();
        $other = Project::factory()->create([
            'workspace_id' => $project->workspace_id,
            'local_host_id' => $project->local_host_id,
            'created_by' => $owner->id,
        ]);
        $archive = ProjectSourceArchive::factory()->create([
            'workspace_id' => $other->workspace_id,
            'project_id' => $other->id,
            'uploaded_by' => $owner->id,
        ]);

        $this->assertSqlstate('23514', fn () => Deployment::query()->create([
            'environment_id' => $environment->id,
            'workspace_id' => $environment->workspace_id,
            'source_type' => ProjectSourceType::Archive,
            'source_archive_id' => $archive->id,
            'commit_sha' => null,
            'trigger_type' => 'manual',
            'actor_id' => $owner->id,
            'status' => DeploymentStatus::Queued,
            'artifact_digest' => $archive->sha256,
            'build_status' => PhaseStatus::Pending,
            'health_status' => PhaseStatus::Pending,
            'rollback_status' => RollbackStatus::None,
            'plan_hash' => hash('sha256', 'cross-project-archive'),
        ]));
    }

    /**
     * @return array{0: User, 1: Project, 2: Environment}
     */
    private function projectGraph(MembershipRole $role = MembershipRole::Owner): array
    {
        [$user, $workspace] = $this->panelAccount($role, setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'local-host']);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'created_by' => $user->id,
        ]);
        $environment = Environment::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
        ]);

        return [$user, $project, $environment];
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function uploadedZip(string $name, array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rahandaz-upload-');
        $this->assertNotFalse($path);
        $this->temporaryFiles[] = $path;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        foreach ($entries as $entry => $contents) {
            $this->assertTrue($zip->addFromString($entry, $contents));
            $this->assertTrue($zip->setMtimeName($entry, 946684800));
        }

        $this->assertTrue($zip->close());

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }
}
