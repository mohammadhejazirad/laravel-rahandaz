<?php

declare(strict_types=1);

namespace App\Domain\Backups\Enums;

enum BackupType: string
{
    case Database = 'database';
    case ControlPlane = 'control_plane';
    case Config = 'config';
    case Uploads = 'uploads';
}
