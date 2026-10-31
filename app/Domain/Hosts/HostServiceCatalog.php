<?php

declare(strict_types=1);

namespace App\Domain\Hosts;

final class HostServiceCatalog
{
    /**
     * @return list<array{unit: string, label: string, actions: list<string>, stop_blocked: string|null}>
     */
    public function services(): array
    {
        return [
            $this->row('nginx', 'Nginx', true, true),
            $this->row('docker', 'Docker', false, false),
            $this->row('php8.4-fpm', 'PHP 8.4', true, true),
            $this->row('php8.3-fpm', 'PHP 8.3', true, true),
            $this->row('php8.2-fpm', 'PHP 8.2', true, true),
            $this->row('mysql', 'MySQL', false, false),
            $this->row('mariadb', 'MariaDB', false, false),
            $this->row('postgresql', 'PostgreSQL', true, true),
            $this->row('redis-server', 'Redis', true, false),
        ];
    }

    /**
     * @return array{unit: string, label: string, actions: list<string>, stop_blocked: string|null}|null
     */
    public function find(string $unit): ?array
    {
        foreach ($this->services() as $service) {
            if ($service['unit'] === $unit) {
                return $service;
            }
        }

        return null;
    }

    /**
     * @return array{unit: string, label: string, actions: list<string>, stop_blocked: string|null}
     */
    private function row(string $unit, string $label, bool $protectedStop, bool $reload): array
    {
        $actions = ['status', 'start', 'restart'];
        if (! $protectedStop) {
            $actions[] = 'stop';
        }
        if ($reload) {
            $actions[] = 'reload';
        }

        return [
            'unit' => $unit,
            'label' => $label,
            'actions' => $actions,
            'stop_blocked' => $protectedStop ? 'توقف این سرویس راه‌انداز از پنل رد می‌شود تا ورود و دادهٔ پنل قطع نشود.' : null,
        ];
    }
}
