<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

use App\Infrastructure\Secrets\Exceptions\DecryptionFailed;
use RuntimeException;

final class EnvelopeCipher
{
    public const ALGORITHM = 'xchacha20poly1305-ietf';

    public function __construct(private readonly MasterKey $masterKey) {}

    /**
     * @return array<string, int|string>
     */
    public function encrypt(string $plaintext, string $aad, int $keyVersion = 1): array
    {
        $this->assertAvailable();

        $salt = random_bytes(16);
        $dek = random_bytes(32);
        $kek = $this->kek($salt);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $kekNonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $dek);
        $wrappedDek = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($dek, 'dek|'.$keyVersion, $kekNonce, $kek);

        sodium_memzero($dek);
        sodium_memzero($kek);

        if ($ciphertext === false || $wrappedDek === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return [
            'v' => 1,
            'alg' => self::ALGORITHM,
            'kdf' => 'hkdf-sha256',
            'key_version' => $keyVersion,
            'salt' => base64_encode($salt),
            'kek_nonce' => base64_encode($kekNonce),
            'wrapped_dek' => base64_encode($wrappedDek),
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    public function decrypt(array $envelope, string $aad): string
    {
        $this->assertAvailable();

        if (($envelope['v'] ?? null) !== 1 || ($envelope['alg'] ?? null) !== self::ALGORITHM) {
            throw new DecryptionFailed;
        }

        $keyVersion = $envelope['key_version'] ?? null;

        if (! is_int($keyVersion)) {
            throw new DecryptionFailed;
        }

        $salt = $this->decode($envelope['salt'] ?? null);
        $kekNonce = $this->decode($envelope['kek_nonce'] ?? null);
        $wrappedDek = $this->decode($envelope['wrapped_dek'] ?? null);
        $nonce = $this->decode($envelope['nonce'] ?? null);
        $ciphertext = $this->decode($envelope['ciphertext'] ?? null);

        if ($salt === null || $kekNonce === null || $wrappedDek === null || $nonce === null || $ciphertext === null) {
            throw new DecryptionFailed;
        }

        $kek = $this->kek($salt);
        $dek = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($wrappedDek, 'dek|'.$keyVersion, $kekNonce, $kek);
        sodium_memzero($kek);

        if ($dek === false) {
            throw new DecryptionFailed;
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $aad, $nonce, $dek);
        sodium_memzero($dek);

        if ($plaintext === false) {
            throw new DecryptionFailed;
        }

        return $plaintext;
    }

    private function kek(string $salt): string
    {
        return hash_hkdf('sha256', $this->masterKey->bytes(), 32, 'rahandaz-envelope-kek-v1', $salt);
    }

    private function decode(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }

    private function assertAvailable(): void
    {
        if (! function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            throw new RuntimeException('libsodium XChaCha20-Poly1305 is unavailable.');
        }
    }
}
