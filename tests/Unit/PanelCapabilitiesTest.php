<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Access\PanelSections;
use App\Domain\Catalog\CatalogVersionChoice;
use App\Domain\Catalog\DatabaseInstallGuard;
use App\Domain\Hosts\PhpServerProfileRules;
use App\Domain\Projects\LaravelRuntimeSettings;
use PHPUnit\Framework\TestCase;

final class PanelCapabilitiesTest extends TestCase
{
    public function test_database_versions_default_to_the_latest_and_reject_unpackaged_choices(): void
    {
        $choice = new CatalogVersionChoice;
        $catalog = [
            'mysql' => ['versions' => [
                ['id' => '8.0', 'rank' => 80, 'packaged' => true],
                ['id' => '8.4', 'rank' => 84, 'packaged' => false],
                ['id' => '9.0', 'rank' => 90, 'packaged' => false],
            ]],
            'mongodb' => ['versions' => [
                ['id' => '6.0', 'rank' => 60, 'packaged' => false],
                ['id' => '7.0', 'rank' => 70, 'packaged' => false],
                ['id' => '8.0', 'rank' => 80, 'packaged' => true],
            ]],
        ];
        $this->assertSame('9.0', $choice->latestId($catalog['mysql']['versions']));
        $this->assertSame(['mongodb' => '8.0'], $choice->assert($catalog, ['mongodb'], []));
        try {
            $choice->assert($catalog, ['mysql'], []);
            $this->fail('Unpackaged latest MySQL was accepted.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }

    public function test_mysql_and_mariadb_require_a_user_port_for_the_second_engine(): void
    {
        $guard = new DatabaseInstallGuard;
        $this->assertNull($guard->secondEngine(['mysql'], []));
        $this->assertSame('mariadb', $guard->secondEngine(['mysql', 'mariadb'], []));
        $this->assertSame('mysql', $guard->secondEngine(['mysql'], ['mariadb']));
        $this->assertSame('mariadb', $guard->secondEngine(['mariadb'], ['mysql']));
        $this->assertSame(3307, $guard->assertPort('mariadb', 3307));
        try {
            $guard->assertPort('mariadb', 3306);
            $this->fail('Port 3306 was accepted for the second engine.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }

    public function test_php_profile_rejects_a_post_limit_below_upload(): void
    {
        $rules = new PhpServerProfileRules;
        $values = $rules->normalize([
            'upload_max_filesize' => '8M',
            'post_max_size' => '16M',
            'memory_limit' => '256M',
            'max_execution_time' => 60,
            'max_input_time' => 60,
            'max_input_vars' => 1000,
            'timezone' => 'Asia/Tehran',
        ]);
        $this->assertSame('16M', $values['post_max_size']);
        try {
            $rules->normalize([
                'upload_max_filesize' => '64M',
                'post_max_size' => '8M',
                'memory_limit' => '128M',
                'max_execution_time' => 30,
                'max_input_time' => 60,
                'max_input_vars' => 1000,
                'timezone' => 'Asia/Tehran',
            ]);
            $this->fail('A smaller POST limit was accepted.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }

    public function test_laravel_cards_keep_only_their_own_fields(): void
    {
        $settings = new LaravelRuntimeSettings;
        $this->assertSame(['schedule_enabled' => true], $settings->normalize('schedule', ['enabled' => true]));
        $this->assertSame('bootstrap/ssr/ssr.js', $settings->normalize('inertia', ['enabled' => false, 'path' => 'bootstrap/ssr/ssr.js'])['inertia_ssr_path']);
        $count = count(PanelSections::all());
        $this->assertGreaterThanOrEqual(10, $count);
        $this->assertLessThanOrEqual(20, $count);
        $this->assertSame('php', PanelSections::routeSection('servers.php'));
        $this->assertNull(PanelSections::routeSection('access.switch'));
        $this->assertTrue(PanelSections::allows(null, 'projects', false));
        $this->assertFalse(PanelSections::allows(['dashboard'], 'projects', false));
    }
}
