<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Updates\UpdateChecker;
use Illuminate\Console\Command;
use Throwable;

class CheckForRahandazUpdates extends Command
{
    protected $signature = 'rahandaz:updates:check';

    protected $description = 'Check the configured GitHub release channel and record the result';

    public function handle(UpdateChecker $checker): int
    {
        try {
            $result = $checker->run();
        } catch (Throwable $exception) {
            $this->line('update_check: fail');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
        $this->line('update_check: '.$result->result);
        if ($result->latestVersion !== null) {
            $this->line('latest: '.$result->latestVersion);
        }
        $this->line('update_available: '.($result->updateAvailable ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
