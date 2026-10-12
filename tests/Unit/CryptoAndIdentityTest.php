<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Security\ContentSecurityPolicy;
use App\Infrastructure\Host\AllowlistedHostFileReader;
use App\Infrastructure\Host\OsReleaseParser;
use App\Infrastructure\Secrets\EnvelopeCipher;
use App\Infrastructure\Secrets\Exceptions\DecryptionFailed;
use App\Infrastructure\Secrets\MasterKey;
use App\Infrastructure\Secrets\Redactor;
use App\Infrastructure\Secrets\SecretFile;
use App\Support\Identifiers;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class CryptoAndIdentityTest extends TestCase
{
    public function test_identifiers_are_ordered_uuid_v7_values(): void
    {
        $values = [];

        for ($index = 0; $index < 20; $index++) {
            $values[] = Identifiers::uuid7();
        }

        $sorted = $values;
        sort($sorted);

        $this->assertSame($sorted, $values);
        $this->assertCount(20, array_unique($values));
        $this->assertFalse(Identifiers::isUuidV7((string) Str::uuid()));
        $this->assertFalse(Identifiers::isUuidV7('not-a-uuid'));
    }

    public function test_envelope_round_trip_rejects_tampering_and_reuses_no_nonce(): void
    {
        $cipher = $this->cipher();
        $first = $cipher->encrypt('panel-secret', 'workspace|credential|github');
        $second = $cipher->encrypt('panel-secret', 'workspace|credential|github');

        $this->assertSame('panel-secret', $cipher->decrypt($first, 'workspace|credential|github'));
        $this->assertNotSame($first['nonce'], $second['nonce']);
        $this->assertStringNotContainsString('panel-secret', json_encode($first, JSON_THROW_ON_ERROR));

        $tampered = $first;
        $tampered['ciphertext'] = base64_encode(random_bytes(24));

        $this->expectException(DecryptionFailed::class);
        $cipher->decrypt($tampered, 'workspace|credential|github');
    }

    public function test_envelope_rejects_the_wrong_aad_and_the_wrong_key(): void
    {
        $cipher = $this->cipher();
        $envelope = $cipher->encrypt('panel-secret', 'bound-aad');

        try {
            $cipher->decrypt($envelope, 'other-aad');
            $this->fail('The wrong AAD was accepted.');
        } catch (DecryptionFailed) {
            $this->assertTrue(true);
        }

        $other = $this->cipher();

        $this->expectException(DecryptionFailed::class);
        $other->decrypt($envelope, 'bound-aad');
    }

    public function test_redactor_removes_bearer_tokens_url_userinfo_and_assignments(): void
    {
        $redacted = (new Redactor)->redact('Bearer abc.def https://user:pass@example.test password=hunter2 token: raw');

        $this->assertStringNotContainsString('abc.def', $redacted);
        $this->assertStringNotContainsString('user:pass@', $redacted);
        $this->assertStringNotContainsString('hunter2', $redacted);
        $this->assertStringNotContainsString('raw', $redacted);
        $this->assertStringContainsString('Bearer [redacted]', $redacted);
    }

    public function test_os_release_parser_reads_quoted_values(): void
    {
        $parsed = (new OsReleaseParser)->parse("NAME=\"Ubuntu\"\nVERSION_ID=\"24.04\"\nID=ubuntu\n# comment\n");

        $this->assertSame('Ubuntu', $parsed['name']);
        $this->assertSame('24.04', $parsed['version']);
        $this->assertSame('ubuntu', $parsed['id']);
    }

    public function test_host_reader_rejects_paths_outside_the_allowlist(): void
    {
        $reader = new AllowlistedHostFileReader;

        $this->expectException(InvalidArgumentException::class);
        $reader->read('/etc/passwd');
    }

    public function test_production_csp_has_no_eval_and_no_inline_script(): void
    {
        $policy = ContentSecurityPolicy::production();

        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $policy);
    }

    private function cipher(): EnvelopeCipher
    {
        $path = sys_get_temp_dir().'/rahandaz-envelope-'.bin2hex(random_bytes(4)).'.key';
        SecretFile::write($path, base64_encode(random_bytes(32))."\n");

        return new EnvelopeCipher(new MasterKey($path));
    }
}
