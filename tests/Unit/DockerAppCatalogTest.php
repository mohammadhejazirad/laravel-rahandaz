<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Docker\ComposeEnvReader;
use App\Domain\Docker\DockerAppCatalog;
use PHPUnit\Framework\TestCase;

final class DockerAppCatalogTest extends TestCase
{
    public function test_templates_keep_secrets_out_of_the_compose_text(): void
    {
        $reader = new ComposeEnvReader;
        $catalog = new DockerAppCatalog;
        $this->assertGreaterThanOrEqual(10, count($catalog->all()));

        foreach ($catalog->all() as $app) {
            $this->assertFalse($reader->hasInlineSecret($app['compose']), $app['id']);
            $declared = array_column($app['env'], 'name');
            sort($declared);
            $this->assertSame($declared, $reader->keys($app['compose']), $app['id']);
        }
    }

    public function test_custom_compose_keys_are_read_and_inline_passwords_are_refused(): void
    {
        $reader = new ComposeEnvReader;
        $this->assertSame(['APP_PORT', 'DB_PASSWORD'], $reader->keys("services:\n  app:\n    environment:\n      DB_PASSWORD: \${DB_PASSWORD}\n    ports:\n      - \"\${APP_PORT}:80\"\n"));
        $this->assertTrue($reader->hasInlineSecret("services:\n  app:\n    environment:\n      DB_PASSWORD: supersecretvalue\n"));
        $this->assertTrue($reader->hasInlineSecret("services:\n  app:\n    environment:\n      - DB_PASSWORD=supersecretvalue\n"));
        $this->assertTrue($reader->hasInlineSecret("gitlab_rails['initial_root_password'] = 'literalpassword'\n"));
        $this->assertFalse($reader->hasInlineSecret("gitlab_rails['initial_root_password'] = '\${GITLAB_ROOT_PASSWORD}'\n"));
    }
}
