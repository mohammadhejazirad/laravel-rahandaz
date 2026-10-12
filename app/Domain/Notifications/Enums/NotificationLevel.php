<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum NotificationLevel: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
}
