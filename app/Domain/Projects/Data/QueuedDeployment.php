<?php

declare(strict_types=1);

namespace App\Domain\Projects\Data;

use App\Models\Deployment;
use App\Models\OperationJob;

final readonly class QueuedDeployment
{
    public function __construct(
        public Deployment $deployment,
        public OperationJob $operation,
    ) {}
}
