<?php

declare(strict_types=1);

namespace App\Domain\Settings\Enums;

enum SettingValueType: string
{
    case String = 'string';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case CredentialRef = 'credential_ref';
}
