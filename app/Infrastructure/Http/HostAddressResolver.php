<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

interface HostAddressResolver
{
    /**
     * @return list<string>
     */
    public function addresses(string $host): array;
}
