<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum ProjectType: string
{
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
}
