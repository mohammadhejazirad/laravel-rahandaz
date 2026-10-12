<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

final readonly class OperationResult
{
    /**
     * @param  array<string, mixed>  $observedState
     * @param  array<string, mixed>  $plan
     */
    public function __construct(
        public string $schemaVersion,
        public string $operation,
        public string $operationVersion,
        public string $executionId,
        public string $status,
        public ?string $errorCode,
        public array $observedState,
        public array $plan,
        public bool $mutated,
        public string $diagnostics,
    ) {}
}
