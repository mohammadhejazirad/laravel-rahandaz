<?php

declare(strict_types=1);

namespace App\Domain\Projects\Data;

use App\Models\Deployment;
use App\Models\OperationJob;
use App\Models\ProjectSourceArchive;

final readonly class StoredArchiveDeployment
{
    public function __construct(
        public ProjectSourceArchive $archive,
        public Deployment $deployment,
        public OperationJob $operationJob,
        public bool $reusedArchive,
    ) {}
}
