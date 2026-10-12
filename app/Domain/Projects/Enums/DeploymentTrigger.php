<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum DeploymentTrigger: string
{
    case Manual = 'manual';
    case WebhookPush = 'webhook_push';
    case BranchUpdate = 'branch_update';
    case Scheduled = 'scheduled';
    case Retry = 'retry';
    case Rollback = 'rollback';
}
