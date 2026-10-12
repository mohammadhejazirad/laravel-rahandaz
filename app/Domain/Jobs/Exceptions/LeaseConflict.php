<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Exceptions;

use RuntimeException;

final class LeaseConflict extends RuntimeException {}
