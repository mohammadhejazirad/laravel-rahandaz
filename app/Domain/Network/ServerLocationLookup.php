<?php

declare(strict_types=1);

namespace App\Domain\Network;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads the machine's public address and asks Chabokan, then free fallbacks.
 * A failure stays unknown. It does not change apt sources or install packages.
 */
final class ServerLocationLookup
{
    public function forPanel(): array
    {
        if (app()->runningUnitTests() || ! (bool) config('rahandaz.location.enabled', true)) {
            return $this->unknown();
        }

        $cached = Cache::get('rahandaz.server-location');
        if (is_array($cached) && ($cached['status'] ?? null) !== null) {
            return $cached;
        }

        $result = $this->probe();
        $seconds = ($result['status'] ?? '') === 'known'
            ? (int) config('rahandaz.location.cache_seconds', 21600)
            : (int) config('rahandaz.location.failure_cache_seconds', 600);
        Cache::put('rahandaz.server-location', $result, now()->addSeconds(max(60, $seconds)));

        return $result;
    }

    /**
     * @return array<string, bool|string|null>
     */
    public function probe(): array
    {
        try {
            $ip = $this->publicIp();
            if ($ip === null) {
                return $this->unknown();
            }
            foreach (['chabokan', 'ipwho', 'ip-api'] as $source) {
                $parsed = $this->fromSource($source, $ip);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        } catch (Throwable) {
            return $this->unknown();
        }

        return $this->unknown();
    }

    /**
     * @return array<string, bool|string|null>
     */
    private function unknown(): array
    {
        return [
            'status' => 'unknown',
            'in_iran' => null,
            'country_code' => null,
            'country_label' => 'تشخیص نشد',
            'city' => null,
            'isp' => null,
            'org' => null,
            'asn' => null,
            'public_ip' => null,
            'source' => null,
            'source_label' => null,
            'looked_up_at' => null,
        ];
    }

    private function publicIp(): ?string
    {
        foreach ([
            'https://api.ipify.org',
            'https://ifconfig.me/ip',
            'https://icanhazip.com',
        ] as $url) {
            $body = $this->text($url);
            if ($body !== null && $this->isPublicIp($body)) {
                return $body;
            }
        }

        return null;
    }

    /**
     * @return array<string, bool|string|null>|null
     */
    private function fromSource(string $source, string $ip): ?array
    {
        $url = match ($source) {
            'chabokan' => 'https://chabokan.net/ip/?address='.rawurlencode($ip),
            'ipwho' => 'https://ipwho.is/'.rawurlencode($ip),
            'ip-api' => 'http://ip-api.com/json/'.rawurlencode($ip),
            default => null,
        };
        if ($url === null) {
            return null;
        }
        $json = $this->json($url);
        if ($json === null) {
            return null;
        }

        $record = match ($source) {
            'chabokan' => [
                'country' => $json['country'] ?? null,
                'ip' => $json['ip'] ?? null,
                'city' => $json['city'] ?? null,
                'isp' => $json['isp'] ?? null,
                'org' => $json['org'] ?? null,
                'asn' => $json['asn'] ?? null,
            ],
            'ipwho' => ($json['success'] ?? true) === false ? null : [
                'country' => $json['country_code'] ?? null,
                'ip' => $json['ip'] ?? null,
                'city' => $json['city'] ?? null,
                'isp' => is_array($json['connection'] ?? null) ? ($json['connection']['isp'] ?? null) : null,
                'org' => is_array($json['connection'] ?? null) ? ($json['connection']['org'] ?? null) : null,
                'asn' => is_array($json['connection'] ?? null) ? ($json['connection']['asn'] ?? null) : null,
            ],
            'ip-api' => ($json['status'] ?? '') === 'success' ? [
                'country' => $json['countryCode'] ?? null,
                'ip' => $json['query'] ?? null,
                'city' => $json['city'] ?? null,
                'isp' => $json['isp'] ?? null,
                'org' => $json['org'] ?? null,
                'asn' => $json['as'] ?? null,
            ] : null,
            default => null,
        };
        if (! is_array($record)) {
            return null;
        }
        $country = strtoupper($this->token($record['country'] ?? null, 2));
        $seen = $this->token($record['ip'] ?? null, 64);
        if (strlen($country) !== 2 || ($seen !== '' && $seen !== $ip)) {
            return null;
        }

        return [
            'status' => 'known',
            'in_iran' => $country === 'IR',
            'country_code' => $country,
            'country_label' => $country === 'IR' ? 'ایران' : $country,
            'city' => $this->nullable($record['city'] ?? null, 80),
            'isp' => $this->nullable($record['isp'] ?? null, 120),
            'org' => $this->nullable($record['org'] ?? null, 120),
            'asn' => $this->nullable($record['asn'] ?? null, 40),
            'public_ip' => $ip,
            'source' => $source,
            'source_label' => match ($source) {
                'chabokan' => 'چابکان',
                'ipwho' => 'ipwho.is',
                'ip-api' => 'ip-api.com',
                default => $source,
            },
            'looked_up_at' => now()->utc()->toIso8601ZuluString(),
        ];
    }

    private function text(string $url): ?string
    {
        $response = $this->request($url);
        if ($response === null || ! $response->successful()) {
            return null;
        }
        $body = trim($response->body());
        if ($body === '' || strlen($body) > 128 || str_contains($body, "\n")) {
            return null;
        }

        return $body;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function json(string $url): ?array
    {
        $response = $this->request($url);
        if ($response === null || ! $response->successful()) {
            return null;
        }
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : null;
    }

    private function request(string $url): ?Response
    {
        try {
            return Http::withOptions(['allow_redirects' => false])
                ->connectTimeout(2)
                ->timeout(3)
                ->acceptJson()
                ->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    private function isPublicIp(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        $packed = inet_pton($value);
        if ($packed !== false && strlen($packed) === 4) {
            $second = ord($packed[1]);
            if (ord($packed[0]) === 100 && $second >= 64 && $second <= 127) {
                return false;
            }
        }

        return true;
    }

    private function token(mixed $value, int $limit): string
    {
        if (! is_scalar($value)) {
            return '';
        }
        $text = trim((string) $value);
        if ($text === '' || strlen($text) > $limit || preg_match('/[\x00-\x1f]/', $text) === 1) {
            return '';
        }

        return $text;
    }

    private function nullable(mixed $value, int $limit): ?string
    {
        $text = $this->token($value, $limit);

        return $text === '' ? null : $text;
    }
}
