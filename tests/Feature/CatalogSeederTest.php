<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MirrorEndpoint;
use App\Models\PackageDefinition;
use App\Models\PackageVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_are_idempotent_and_contain_no_accounts_or_trusted_mirrors(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, MirrorEndpoint::query()->where('trust_status', 'trusted')->count());
        $this->assertSame(0, PackageVersion::query()->where('lifecycle', 'active')->count());
        $this->assertSame(0, MirrorEndpoint::query()->where('software_kind', 'pypi')->count());
        $this->assertSame(10, MirrorEndpoint::query()->count());
        $this->assertSame(9, PackageDefinition::query()->count());
        $this->assertSame(
            ['nginx', 'postgresql', 'redis'],
            PackageDefinition::query()->where('control_plane_candidate', true)->orderBy('key')->pluck('key')->all(),
        );
        $this->assertSame(
            'https://mirror.arvancloud.ir/ubuntu/',
            MirrorEndpoint::query()->where('provider', 'arvancloud')->where('software_kind', 'ubuntu_apt')->value('base_url'),
        );
    }
}
