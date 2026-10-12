<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Exceptions;

use RuntimeException;

final class IdempotencyConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Idempotency key was reused with a different request.');
    }
}
