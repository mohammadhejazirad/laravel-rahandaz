<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Exceptions;

use InvalidArgumentException;

final class UnsafePlan extends InvalidArgumentException
{
    public function __construct(string $message = 'Plan contains a forbidden secret-like value.')
    {
        parent::__construct($message);
    }
}
