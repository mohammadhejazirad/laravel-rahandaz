<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets\Exceptions;

use RuntimeException;

final class SecretFilePermissionException extends RuntimeException
{
    public function __construct(public readonly string $path)
    {
        parent::__construct('Secret file is readable by group or others and was removed: '.$path);
    }
}
