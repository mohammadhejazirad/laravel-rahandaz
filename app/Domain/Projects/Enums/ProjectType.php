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
    case StaticReact = 'static-react';
    case StaticVue = 'static-vue';
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
    case Wordpress = 'wordpress';

    public function label(): string
    {
        return match ($this) {
            self::Laravel => 'Laravel',
            self::Php => 'PHP',
            self::StaticHtml => 'HTML',
            self::StaticReact => 'React',
            self::StaticVue => 'Vue',
            self::Node => 'Node.js',
            self::Nuxt => 'Nuxt.js',
            self::Next => 'Next.js',
            self::Python => 'Python',
            self::Django => 'Django',
            self::Flask => 'Flask',
            self::Fastapi => 'FastAPI',
            self::Express => 'Express.js',
            self::Vue => 'Vue.js',
            self::React => 'React.js',
            self::Nest => 'NestJS',
            self::Adonis => 'AdonisJS',
            self::Wordpress => 'WordPress',
        };
    }
}
