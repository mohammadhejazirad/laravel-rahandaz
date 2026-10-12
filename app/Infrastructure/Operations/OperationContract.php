<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

final readonly class OperationContract
{
    public function __construct(
        public string $name,
        public string $version,
        public string $privilege,
        public int $timeoutSeconds,
        public ?string $lock,
        public string $idempotency,
        public bool $mutating,
        public string $script,
    ) {
        if (preg_match('/^[a-z0-9._]+$/', $this->name) !== 1) {
            throw new OperationRejected('UNKNOWN_OPERATION', 'Operation name is not allowlisted.');
        }

        if (! in_array($this->privilege, ['read', 'helper', 'project'], true)) {
            throw new OperationRejected('INVALID_INPUT', 'Operation privilege is not allowlisted.');
        }

        if ($this->timeoutSeconds < 1) {
            throw new OperationRejected('INVALID_INPUT', 'Operation timeout must be positive.');
        }
    }
}
