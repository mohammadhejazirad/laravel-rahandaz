<?php

declare(strict_types=1);

namespace App\Domain\Projects\Exceptions;

use RuntimeException;

final class DeploymentLockConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Another deployment is already changing this environment.');
    }
}
