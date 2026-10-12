<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Git\WebhookSignature;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    public function test_github_and_gitlab_signatures_require_an_equal_length_match(): void
    {
        $signatures = new WebhookSignature;
        $payload = '{"ref":"refs/heads/main"}';
        $secret = 'whsec-test-secret';
        $header = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($signatures->github($payload, $secret, $header));
        $this->assertFalse($signatures->github($payload, $secret, 'sha256=abcd'));
        $this->assertFalse($signatures->github($payload, $secret, $header.'x'));
        $this->assertTrue($signatures->gitlab($secret, $secret));
        $this->assertFalse($signatures->gitlab($secret, 'whsec-test-secreT'));
        $this->assertFalse($signatures->gitlab($secret, 'short'));
    }
}
