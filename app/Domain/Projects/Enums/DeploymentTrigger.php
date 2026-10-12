<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum DeploymentTrigger: string
{
    use HasEnumLabel;

    case Manual = 'manual';
    case WebhookPush = 'webhook_push';
    case BranchUpdate = 'branch_update';
    case Scheduled = 'scheduled';
    case Retry = 'retry';
    case Rollback = 'rollback';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'دستی',
            self::WebhookPush => 'push وب‌هوک',
            self::BranchUpdate => 'به‌روزرسانی شاخه',
            self::Scheduled => 'زمان‌بندی‌شده',
            self::Retry => 'تلاش دوباره',
            self::Rollback => 'بازگشت',
        };
    }
}
