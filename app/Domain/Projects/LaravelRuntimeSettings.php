<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use Illuminate\Validation\ValidationException;

final class LaravelRuntimeSettings
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(string $feature, array $input): array
    {
        return match ($feature) {
            'schedule' => ['schedule_enabled' => $this->bool($input['enabled'] ?? null)],
            'horizon' => ['horizon_enabled' => $this->bool($input['enabled'] ?? null)],
            'maintenance' => ['maintenance_enabled' => $this->bool($input['enabled'] ?? null)],
            'reverb' => [
                'reverb_enabled' => $this->bool($input['enabled'] ?? null),
                'reverb_host' => $this->host($input['host'] ?? ''),
                'reverb_port' => $this->port($input['port'] ?? null),
            ],
            'octane' => [
                'octane_enabled' => $this->bool($input['enabled'] ?? null),
                'octane_server' => $this->server($input['server'] ?? ''),
                'octane_host' => $this->host($input['host'] ?? ''),
                'octane_port' => $this->port($input['port'] ?? null),
                'octane_workers' => $this->workers($input['workers'] ?? null),
            ],
            'inertia' => [
                'inertia_ssr_enabled' => $this->bool($input['enabled'] ?? null),
                'inertia_ssr_path' => $this->script($input['path'] ?? ''),
            ],
            default => throw ValidationException::withMessages(['feature' => 'این بخش برای پروژهٔ لاراول تعریف نشده است.']),
        };
    }

    private function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private function host(mixed $value): string
    {
        $host = is_string($value) ? trim($value) : '';
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]{0,252}$/', $host) !== 1) {
            throw ValidationException::withMessages(['host' => 'میزبان فقط نام یا IP ساده است.']);
        }

        return $host;
    }

    private function port(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^\d{2,5}$/', $value) === 1)) {
            throw ValidationException::withMessages(['port' => 'پورت باید عدد باشد.']);
        }
        $port = (int) $value;
        if ($port < 1024 || $port > 65535) {
            throw ValidationException::withMessages(['port' => 'پورت باید بین ۱۰۲۴ و ۶۵۵۳۵ باشد.']);
        }

        return $port;
    }

    private function workers(mixed $value): int
    {
        $workers = is_numeric($value) ? (int) $value : 0;
        if ($workers < 1 || $workers > 32) {
            throw ValidationException::withMessages(['workers' => 'تعداد worker بین ۱ و ۳۲ است.']);
        }

        return $workers;
    }

    private function server(mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, ['swoole', 'roadrunner'], true)) {
            throw ValidationException::withMessages(['server' => 'سرور Octane باید swoole یا roadrunner باشد.']);
        }

        return $value;
    }

    private function script(mixed $value): string
    {
        $path = is_string($value) ? trim($value) : '';
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_\/.-]{0,160}\.js$/', $path) !== 1 || str_contains($path, '..')) {
            throw ValidationException::withMessages(['path' => 'مسیر SSR باید یک فایل نسبی js داخل پروژه باشد.']);
        }

        return $path;
    }
}
