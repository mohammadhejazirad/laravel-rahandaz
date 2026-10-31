<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Hosts\HostServiceCatalog;
use App\Domain\Projects\DockerLayout;
use PHPUnit\Framework\TestCase;

final class DockerLayoutTest extends TestCase
{
    public function test_layouts_follow_the_files_that_are_actually_present(): void
    {
        $this->assertSame(DockerLayout::COMPOSE_ONLY, DockerLayout::classify(['compose.yml']));
        $this->assertSame(DockerLayout::COMPOSE_ONLY, DockerLayout::classify(['docker-compose.yaml', 'README.md']));
        $this->assertSame(DockerLayout::DOCKERFILE_ONLY, DockerLayout::classify(['Dockerfile']));
        $this->assertSame(DockerLayout::BOTH, DockerLayout::classify(['Dockerfile.prod', 'compose.yaml']));
        $this->assertSame(DockerLayout::ABSENT, DockerLayout::classify(['../compose.yml', 'notes.txt']));
    }

    public function test_actions_match_the_layout(): void
    {
        $this->assertNotContains('build', DockerLayout::actions(DockerLayout::COMPOSE_ONLY));
        $this->assertSame(['status', 'build'], DockerLayout::actions(DockerLayout::DOCKERFILE_ONLY));
        $this->assertContains('down', DockerLayout::actions(DockerLayout::BOTH));
    }

    public function test_panel_services_can_be_restarted_and_protected_ones_cannot_be_stopped(): void
    {
        $catalog = new HostServiceCatalog;
        $nginx = $catalog->find('nginx');
        $docker = $catalog->find('docker');
        $this->assertNotNull($nginx);
        $this->assertNotNull($docker);
        $this->assertContains('restart', $nginx['actions']);
        $this->assertNotContains('stop', $nginx['actions']);
        $this->assertContains('stop', $docker['actions']);
        $this->assertNotContains('stop', $catalog->find('php8.4-fpm')['actions']);
        $this->assertNull($catalog->find('sshd'));
    }
}
