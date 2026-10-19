<?php

declare(strict_types=1);

namespace App\Infrastructure\Executor;

/**
 * General host execution stays closed. The separately enrolled panel-port
 * capability has its own narrow worker and privileged contract.
 */
final class ExecutionGate
{
    public function allowsHostMutation(): bool
    {
        return false;
    }

    public function denialCode(): string
    {
        return config('rahandaz.execution_mode') === 'local'
            ? 'EXECUTOR_NOT_CONFIGURED'
            : 'EXECUTION_DISABLED';
    }

    public function denialMessage(): string
    {
        return $this->denialCode() === 'EXECUTION_DISABLED'
            ? 'Execution mode is disabled. No host command was run.'
            : 'The local executor is not configured. No host command was run.';
    }
}
