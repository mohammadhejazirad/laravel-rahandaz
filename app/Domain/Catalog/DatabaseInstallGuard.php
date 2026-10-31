<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use Illuminate\Validation\ValidationException;

final class DatabaseInstallGuard
{
    /**
     * The engine that must not keep port 3306 when MySQL and MariaDB would overlap.
     *
     * @param  list<string>  $selected
     * @param  list<string>  $installed
     */
    public function secondEngine(array $selected, array $installed): ?string
    {
        $selected = array_values(array_unique(array_intersect($selected, ['mysql', 'mariadb'])));
        $installed = array_values(array_unique(array_intersect($installed, ['mysql', 'mariadb'])));
        $incoming = array_values(array_diff($selected, $installed));
        if (count($selected) < 2 && count($installed) === 0) {
            return null;
        }
        if ($incoming === []) {
            return null;
        }
        if (count($installed) === 1 && in_array($installed[0], $selected, true) === false && count($selected) === 1) {
            return $selected[0];
        }
        if (in_array('mysql', $installed, true) && in_array('mariadb', $incoming, true)) {
            return 'mariadb';
        }
        if (in_array('mariadb', $installed, true) && in_array('mysql', $incoming, true)) {
            return 'mysql';
        }
        if (count($selected) === 2) {
            return 'mariadb';
        }

        return null;
    }

    public function assertPort(?string $engine, mixed $port): ?int
    {
        if ($engine === null) {
            return null;
        }
        if (! is_int($port) && ! (is_string($port) && preg_match('/^\d{4,5}$/', $port) === 1)) {
            throw ValidationException::withMessages(['listen_port' => 'برای دیتابیس دوم یک پورت عددی انتخاب کنید. ۳۳۰۶ برای موتور دیگر می‌ماند.']);
        }
        $value = (int) $port;
        if ($value < 1024 || $value > 65535 || $value === 3306) {
            throw ValidationException::withMessages(['listen_port' => 'پورت دیتابیس دوم باید بین ۱۰۲۴ و ۶۵۵۳۵ باشد و ۳۳۰۶ نباشد.']);
        }

        return $value;
    }
}
