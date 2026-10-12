<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets\Exceptions;

use RuntimeException;

final class DecryptionFailed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ciphertext could not be opened.');
    }
}
