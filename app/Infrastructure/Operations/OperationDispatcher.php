<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use App\Infrastructure\Executor\ExecutionGate;

/**
 * Validates an operation and then stops.
 * Host commands stay behind the closed execution gate and the uninstalled helper socket.
 */
final class OperationDispatcher
{
    public function __construct(
        private readonly ExecutionGate $gate,
        private readonly OperationCatalog $catalog,
        private readonly OperationInputGuard $guard,
    ) {}

    /**
     * @param  array<mixed>  $input
     */
    public function dispatch(string $operation, array $input): never
    {
        $this->catalog->get($operation);
        $this->guard->assert($input);

        if (! $this->gate->allowsHostMutation()) {
            throw new HostExecutionDenied($this->gate->denialCode(), $this->gate->denialMessage());
        }

        throw new HostExecutionDenied(
            'EXECUTOR_NOT_CONFIGURED',
            'The privileged helper socket is not installed. No host command was run.',
        );
    }
}
