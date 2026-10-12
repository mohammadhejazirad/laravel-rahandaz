<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

enum WebhookProcessingState: string
{
    case Received = 'received';
    case Rejected = 'rejected';
    case Queued = 'queued';
    case Processed = 'processed';
    case Ignored = 'ignored';
}
