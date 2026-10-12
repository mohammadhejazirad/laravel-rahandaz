<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets\Exceptions;

use RuntimeException;

final class MasterKeyUnavailable extends RuntimeException {}
