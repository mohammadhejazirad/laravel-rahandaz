<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

enum GrantResourceType: string
{
    case Project = 'project';
    case Environment = 'environment';
    case ManagedService = 'managed_service';
    case DatabaseInstance = 'database_instance';
    case GitConnection = 'git_connection';
    case LocalHost = 'local_host';
}
