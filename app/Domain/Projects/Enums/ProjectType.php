<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum ProjectType: string
{
    use HasEnumLabel;

    case Laravel = 'laravel';
    case Php = 'php';
    case StaticHtml = 'static';
    case Node = 'node';
    case Nuxt = 'nuxt';
    case Next = 'next';
    case Python = 'python';
    case Django = 'django';
    case Flask = 'flask';
    case Fastapi = 'fastapi';
    case Express = 'express';
    case Vue = 'vue';
    case React = 'react';
    case Nest = 'nest';
    case Adonis = 'adonis';

    public function label(): string
    {
        return match ($this) {
            self::Laravel => 'Laravel',
            self::Php => 'PHP',
            self::StaticHtml => 'ایستا',
            self::Node => 'Node.js',
            self::Nuxt => 'Nuxt',
            self::Next => 'Next.js',
            self::Python => 'Python',
            self::Django => 'Django',
            self::Flask => 'Flask',
            self::Fastapi => 'FastAPI',
            self::Express => 'Express',
            self::Vue => 'Vue',
            self::React => 'React',
            self::Nest => 'Nest',
            self::Adonis => 'Adonis',
        };
    }
}
