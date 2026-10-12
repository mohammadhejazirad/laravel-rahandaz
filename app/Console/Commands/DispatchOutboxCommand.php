<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Jobs\OutboxDispatcher;
use Illuminate\Console\Command;

class DispatchOutboxCommand extends Command
{
    protected $signature = 'rahandaz:outbox:dispatch {--limit=50 : Maximum outbox rows to publish}';

    protected $description = 'Publish pending operation outbox rows to the Redis queue';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $published = $dispatcher->publishPending(max(1, (int) $this->option('limit')));
        $this->info('published: '.$published);

        return self::SUCCESS;
    }
}
