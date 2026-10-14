<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Http\Exceptions\UnsafeOutboundUrl;
use Illuminate\Support\Facades\DB;

final class PublicHttpsGuard
{
    public function __construct(private readonly HostAddressResolver $resolver) {}

    public function pin(string $url): string
    {
        $row = DB::selectOne('select rahandaz_public_https_url(?) as ok', [$url]);

        if (! (bool) ($row->ok ?? false)) {
            throw new UnsafeOutboundUrl('نشانی HTTPS عمومی نیست.');
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $addresses = $this->resolver->addresses($host);

        if ($addresses === []) {
            throw new UnsafeOutboundUrl('نام میزبان به نشانی عمومی ترجمه نشد.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublic($address)) {
                throw new UnsafeOutboundUrl('نام میزبان به نشانی غیرعمومی ترجمه شد.');
            }
        }

        return $addresses[0];
    }

    public function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        $blocked = strlen($packed) === 4 ? [
            '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
            '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
            '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
            '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        ] : ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'];

        // Only global unicast IPv6; also excludes IPv4-mapped, link-local and ULA.
        if (strlen($packed) === 16 && ! $this->inNetwork($packed, '2000::/3')) {
            return false;
        }
        foreach ($blocked as $network) {
            if ($this->inNetwork($packed, $network)) {
                return false;
            }
        }

        return true;
    }

    private function inNetwork(string $address, string $network): bool
    {
        [$base, $bits] = explode('/', $network);
        $prefix = inet_pton($base);
        $whole = intdiv((int) $bits, 8);
        $remaining = (int) $bits % 8;

        return $prefix !== false && substr($address, 0, $whole) === substr($prefix, 0, $whole)
            && ($remaining === 0 || (ord($address[$whole]) & (255 << (8 - $remaining))) === (ord($prefix[$whole]) & (255 << (8 - $remaining))));
    }
}
