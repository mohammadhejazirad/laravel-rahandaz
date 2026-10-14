<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Exceptions;

use RuntimeException;

final class UnsafeOutboundUrl extends RuntimeException {}
