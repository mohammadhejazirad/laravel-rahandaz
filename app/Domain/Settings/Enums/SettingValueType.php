<?php

declare(strict_types=1);

namespace App\Domain\Settings\Enums;

use App\Support\HasEnumLabel;

enum SettingValueType: string
{
    use HasEnumLabel;

    case String = 'string';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case CredentialRef = 'credential_ref';

    public function label(): string
    {
        return match ($this) {
            self::String => 'متن',
            self::Boolean => 'بولی',
            self::Integer => 'عدد صحیح',
            self::CredentialRef => 'ارجاع اعتبارنامه',
        };
    }
}
