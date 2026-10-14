<?php

declare(strict_types=1);

use App\Domain\Jobs\OperationRunner;
use App\Domain\Projects\DeploymentWorkflow;
use App\Models\Environment;
use App\Models\LocalHost;
use App\Models\Membership;
use App\Models\Project;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Workspace;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || ! str_starts_with((string) config('database.connections.pgsql.database'), 'rahandaz_v1_ui_') || User::query()->exists()) {
    throw new RuntimeException('E2E seed requires an empty isolated testing database.');
}
$output = getenv('RAHANDAZ_E2E_CREDENTIAL_FILE');
if (! is_string($output) || ! str_starts_with($output, '/tmp/') || is_file($output) || is_link($output)) {
    throw new RuntimeException('A new private temporary credential file is required.');
}
$password = bin2hex(random_bytes(24));
$owner = User::factory()->create(['username' => 'e2e_owner', 'password' => $password]);
UserProfile::factory()->create(['user_id' => $owner->id, 'display_name' => 'مالک محیط آزمایشی', 'force_password_change' => false]);
$workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'setup_completed_at' => now()]);
Membership::factory()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id, 'role' => 'owner']);
$host = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'display_name' => 'میزبان آزمایشی؛ اجرای عملیات خاموش']);
$project = Project::factory()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'created_by' => $owner->id, 'name' => 'پروژه آزمایشی']);
$environment = Environment::factory()->create(['project_id' => $project->id]);
$queued = app(DeploymentWorkflow::class)->queueManual($environment, $owner, str_repeat('a', 40), null, null);
app(OperationRunner::class)->run($queued->operation->id);
umask(0077);
$handle = fopen($output, 'x');
if ($handle === false) {
    throw new RuntimeException('Cannot create E2E credential file.');
}
fwrite($handle, json_encode(['identifier' => $owner->username, 'password' => $password, 'deployment_uuid' => $queued->deployment->uuid, 'project_uuid' => $project->uuid, 'host_uuid' => $host->uuid], JSON_THROW_ON_ERROR));
fclose($handle);
echo "E2E fixture ready; no host operation executed.\n";
