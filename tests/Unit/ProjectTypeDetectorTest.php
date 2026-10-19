<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\ProjectTypeDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectTypeDetectorTest extends TestCase
{
    private ProjectTypeDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new ProjectTypeDetector;
    }

    #[DataProvider('manifests')]
    public function test_root_manifests_suggest_one_application_type(?ProjectType $expected, array $names, array $files): void
    {
        $this->assertSame($expected, $this->detector->detect($names, $files));
    }

    public static function manifests(): array
    {
        return [
            'laravel composer' => [ProjectType::Laravel, ['composer.json', 'artisan'], ['composer.json' => '{"require":{"laravel/framework":"^11.0"}}']],
            'laravel wins over vite' => [ProjectType::Laravel, ['artisan', 'package.json', 'vite.config.js'], ['package.json' => '{"devDependencies":{"vite":"^6.0","laravel-vite-plugin":"^1.0"}}']],
            'wordpress file' => [ProjectType::Wordpress, ['wp-config.php', 'index.php'], []],
            'wordpress package' => [ProjectType::Wordpress, ['composer.json'], ['composer.json' => '{"require":{"johnpbloch/wordpress":"^6.0"}}']],
            'next' => [ProjectType::Next, ['package.json', 'next.config.ts'], ['package.json' => '{"dependencies":{"next":"^15.0","react":"^19.0"}}']],
            'nuxt' => [ProjectType::Nuxt, ['package.json', 'nuxt.config.ts'], ['package.json' => '{"dependencies":{"nuxt":"^3.0","vue":"^3.0"}}']],
            'adonis' => [ProjectType::Adonis, ['package.json', 'adonisrc.ts'], ['package.json' => '{"dependencies":{"@adonisjs/core":"^6.0"}}']],
            'nest' => [ProjectType::Nest, ['package.json', 'nest-cli.json'], ['package.json' => '{"dependencies":{"@nestjs/core":"^10.0"}}']],
            'express beats react' => [ProjectType::Express, ['package.json'], ['package.json' => '{"dependencies":{"express":"^4.0","react":"^19.0"}}']],
            'static react' => [ProjectType::StaticReact, ['package.json', 'index.html', 'vite.config.ts'], ['package.json' => '{"dependencies":{"react":"^19.0","vite":"^6.0"}}']],
            'node react' => [ProjectType::React, ['package.json', 'server.js'], ['package.json' => '{"dependencies":{"react":"^19.0"}}']],
            'static vue' => [ProjectType::StaticVue, ['package.json', 'index.html'], ['package.json' => '{"dependencies":{"vue":"^3.0","vite":"^6.0"}}']],
            'django' => [ProjectType::Django, ['manage.py', 'requirements.txt'], ['requirements.txt' => "django==5.0\n"]],
            'fastapi' => [ProjectType::Fastapi, ['pyproject.toml', 'main.py'], ['pyproject.toml' => "[project]\ndependencies=['fastapi']\n"]],
            'flask' => [ProjectType::Flask, ['requirements.txt', 'app.py'], ['requirements.txt' => "flask\n"]],
            'plain php' => [ProjectType::Php, ['composer.json', 'index.php'], ['composer.json' => '{"require":{"php":"^8.4"}}']],
            'plain python' => [ProjectType::Python, ['requirements.txt', 'main.py'], ['requirements.txt' => "requests\n"]],
            'node' => [ProjectType::Node, ['package.json'], ['package.json' => '{"dependencies":{"lodash":"^4.0"}}']],
            'static html' => [ProjectType::StaticHtml, ['index.html', 'style.css'], []],
            'unknown' => [null, ['README.md'], []],
        ];
    }
}
