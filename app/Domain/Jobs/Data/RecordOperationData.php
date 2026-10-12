<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Data;

final readonly class RecordOperationData
{
    /**
     * @param  array<string, mixed>  $plan
     */
    public function __construct(
        public int|string $workspaceId,
        public int|string $actorId,
        public string $type,
        public string $idempotencyKey,
        public array $plan,
        public bool $mutatesHost,
    ) {}
}
