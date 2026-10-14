<?php

declare(strict_types=1);

namespace App\Domain\Backups\Exceptions;

use RuntimeException;

final class BackupRejected extends RuntimeException {}
