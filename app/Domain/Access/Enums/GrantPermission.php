<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

enum GrantPermission: string
{
    case View = 'view';
    case Deploy = 'deploy';
    case Operate = 'operate';
    case ManageSecrets = 'manage_secrets';
    case Restore = 'restore';
}
