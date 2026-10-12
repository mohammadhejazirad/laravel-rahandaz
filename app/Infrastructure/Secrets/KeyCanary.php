<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

use App\Infrastructure\Secrets\Exceptions\DecryptionFailed;
use App\Infrastructure\Secrets\Exceptions\MasterKeyUnavailable;

final class KeyCanary
{
    public const PLAINTEXT = 'rahandaz-key-canary-v1';

    public const AAD = 'rahandaz|canary|v1';

    public function __construct(private readonly EnvelopeCipher $cipher) {}

    public function write(string $path): void
    {
        $envelope = $this->cipher->encrypt(self::PLAINTEXT, self::AAD);
        SecretFile::write($path, json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function verify(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return false;
        }

        try {
            return hash_equals(self::PLAINTEXT, $this->cipher->decrypt($decoded, self::AAD));
        } catch (DecryptionFailed|MasterKeyUnavailable) {
            return false;
        }
    }
}
