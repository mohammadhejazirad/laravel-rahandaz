<?php

declare(strict_types=1);

namespace App\Domain\Git;

final class WebhookSignature
{
    public function github(string $payload, string $secret, string $header): bool
    {
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        if (strlen($header) !== strlen($expected)) {
            return false;
        }

        return hash_equals($expected, $header);
    }

    public function gitlab(string $secret, string $token): bool
    {
        if (strlen($secret) !== strlen($token)) {
            return false;
        }

        return hash_equals($secret, $token);
    }
}
