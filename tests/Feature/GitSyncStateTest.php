<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Git\GitSyncState;
use App\Models\GitConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class GitSyncStateTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    public function test_sync_remains_pending_after_http_response_until_a_terminal_audit_event(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $this->actingAs($owner)->post(route('git.sync', $connection))->assertRedirect();
        $state = app(GitSyncState::class);
        $this->assertTrue($state->pending($connection));
        $this->get(route('git.show', $connection))->assertInertia(fn (Assert $page) => $page->where('connection.sync_pending', true));
        foreach (['git.sync_started', 'git.repositories_page', 'git.test'] as $action) {
            app(AuditRecorder::class)->record($workspace, $owner, $action, 'git_connection', $connection->uuid, AuditResult::Success);
            $this->assertTrue($state->pending($connection));
        }
        app(AuditRecorder::class)->record($workspace, $owner, 'git.sync', 'git_connection', $connection->uuid, AuditResult::Success);
        $this->assertFalse($state->pending($connection));
        $this->get(route('git.show', $connection))->assertInertia(fn (Assert $page) => $page->where('connection.sync_pending', false));
        app(AuditRecorder::class)->record($workspace, $owner, 'git.sync_requested', 'git_connection', $connection->uuid, AuditResult::Success);
        $this->assertTrue($state->pending($connection));
        app(AuditRecorder::class)->record($workspace, $owner, 'git.sync_failed', 'git_connection', $connection->uuid, AuditResult::Failure);
        $this->assertFalse($state->pending($connection));
    }

    public function test_another_workspace_cannot_clear_or_create_pending_progress(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        [$foreignOwner, $foreignWorkspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $state = app(GitSyncState::class);
        app(AuditRecorder::class)->record($foreignWorkspace, $foreignOwner, 'git.sync_requested', 'git_connection', $connection->uuid, AuditResult::Success);
        $this->assertFalse($state->pending($connection));
        app(AuditRecorder::class)->record($workspace, $owner, 'git.sync_requested', 'git_connection', $connection->uuid, AuditResult::Success);
        app(AuditRecorder::class)->record($foreignWorkspace, $foreignOwner, 'git.sync', 'git_connection', $connection->uuid, AuditResult::Success);
        $this->assertTrue($state->pending($connection));
    }
}
