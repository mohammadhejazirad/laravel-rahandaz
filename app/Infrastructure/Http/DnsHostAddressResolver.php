<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class DnsHostAddressResolver implements HostAddressResolver
{
    public function addresses(string $host): array
    {
        if (function_exists('dns_get_record') === false) {
            return [];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (is_string($record['ip'] ?? null)) {
                $addresses[] = $record['ip'];
            }

            if (is_string($record['ipv6'] ?? null)) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
