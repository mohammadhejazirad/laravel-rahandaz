<?php

declare(strict_types=1);

namespace App\Domain\Projects\Exceptions;

use RuntimeException;

final class DeploymentRejected extends RuntimeException {}
