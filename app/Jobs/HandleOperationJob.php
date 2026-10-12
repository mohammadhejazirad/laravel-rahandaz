<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Jobs\OperationRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HandleOperationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(public string $operationJobId)
    {
        $this->onQueue('control');
    }

    public function handle(OperationRunner $runner): void
    {
        $runner->run($this->operationJobId);
    }
}
