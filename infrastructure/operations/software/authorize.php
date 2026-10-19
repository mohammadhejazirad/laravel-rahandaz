<?php

declare(strict_types=1);
use App\Domain\Access\OperationAccess;
use App\Domain\Settings\PanelListener;
use App\Models\LocalHost;
use App\Models\OperationJob;
use Illuminate\Contracts\Console\Kernel;

// Called by the root-owned helper as the service UID. Never accepts PHP or shell code.
chdir('/var/lib/rahandaz/current');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $input = json_decode(stream_get_contents(STDIN, 4097), true, flags: JSON_THROW_ON_ERROR);
    $job = OperationJob::query()->where('uuid', $input['execution_id'] ?? '')->firstOrFail();
    $host = LocalHost::query()->forWorkspace($job->workspace_id)->find($job->resource_id);
    $allowed = in_array($job->type, ['setup.initialize', 'software.install'], true)
        && in_array($job->status->value, ['running', 'waiting_retry'], true)
        && ! $job->cancel_requested && $job->resource_type === 'local_host' && $host?->slug === 'local-host'
        && app(OperationAccess::class)->mayExecute($job)
        && ($job->plan['input']['packages'] ?? null) === ($input['packages'] ?? null)
        && ($job->plan['input']['contract_version'] ?? null) === '1'
        && ($job->type === 'setup.initialize') === ($input['operation'] === 'setup.initialize');
    if ($job->type === 'setup.initialize') {
        $allowed = $allowed && $job->actor !== null && app(PanelListener::class)->owner($job->actor, $job->workspace);
    }
    exit($allowed ? 0 : 1);
} catch (Throwable) {
    exit(1);
}
