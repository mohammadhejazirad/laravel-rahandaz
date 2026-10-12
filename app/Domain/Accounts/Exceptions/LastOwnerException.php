<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Exceptions;

use RuntimeException;

final class LastOwnerException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The last owner cannot be removed or demoted.');
    }
}
