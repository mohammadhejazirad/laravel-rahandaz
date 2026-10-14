<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Http\HostAddressResolver;
use App\Infrastructure\Http\PublicHttpsGuard;
use PHPUnit\Framework\TestCase;

class PublicHttpsGuardTest extends TestCase
{
    public function test_special_and_tunnel_addresses_cannot_reach_internal_services(): void
    {
        $resolver = new class implements HostAddressResolver
        {
            public function addresses(string $host): array
            {
                return [];
            }
        };
        $guard = new PublicHttpsGuard($resolver);
        foreach (['127.0.0.1', '10.0.0.1', '100.64.1.1', '169.254.169.254', '192.0.2.1', '198.18.0.1', '224.0.0.1', '::1', '::ffff:127.0.0.1', '::ffff:8.8.8.8', 'fe80::1', 'fc00::1', '2001:db8::1', '2002:7f00:1::1', 'not-an-ip'] as $ip) {
            self::assertFalse($guard->isPublic($ip), $ip);
        }
        foreach (['8.8.8.8', '1.1.1.1', '2606:4700:4700::1111'] as $ip) {
            self::assertTrue($guard->isPublic($ip), $ip);
        }
    }
}
