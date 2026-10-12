<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';
    case Archived = 'archived';
}
