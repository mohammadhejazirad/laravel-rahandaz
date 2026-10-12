<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\OutboxDispatcher;
use App\Domain\Jobs\RecordOperation;
use App\Models\OperationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class RedisQueueTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    public function test_redis_worker_fail_closes_a_queued_operation(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $job = app(RecordOperation::class)->record(new RecordOperationData(
            workspaceId: $workspace->id,
            actorId: $user->id,
            type: 'host.noop',
            idempotencyKey: 'redis-once',
            plan: ['target' => 'none'],
            mutatesHost: false,
        ));

        config(['queue.default' => 'redis']);
        $redis = Redis::connection('queue');
        foreach (['queues:control', 'queues:control:notify', 'queues:control:reserved', 'queues:control:delayed'] as $key) {
            $redis->del($key);
        }

        $this->assertSame(1, app(OutboxDispatcher::class)->publishPending());
        $this->assertSame(0, Artisan::call('queue:work', [
            'connection' => 'redis',
            '--once' => true,
            '--queue' => 'control',
            '--sleep' => 0,
        ]));

        $fresh = OperationJob::query()->findOrFail($job->id);
        $this->assertSame(JobStatus::Failed, $fresh->status);
        $this->assertSame('EXECUTION_DISABLED', $fresh->error_code);
        $this->assertSame(0, OperationJob::query()->where('status', JobStatus::Succeeded)->count());
    }
}
