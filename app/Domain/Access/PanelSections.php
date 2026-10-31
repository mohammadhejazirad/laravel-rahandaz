<?php

declare(strict_types=1);

namespace App\Domain\Access;

final class PanelSections
{
    /**
     * Coarse areas, aligned with the sidebar. Profile and tutorials stay open.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'dashboard' => 'داشبورد',
            'projects' => 'پروژه‌ها',
            'git' => 'حساب‌های گیت',
            'deployments' => 'استقرارها',
            'servers' => 'میزبان محلی',
            'php' => 'PHP سرور',
            'catalog' => 'نرم‌افزارها',
            'domains' => 'دامنه‌ها',
            'databases' => 'دیتابیس‌ها',
            'monitoring' => 'پایش',
            'jobs' => 'عملیات و لاگ‌ها',
            'backups' => 'پشتیبان‌گیری',
            'audit' => 'گزارش فعالیت',
            'notifications' => 'اعلان‌ها',
            'mirrors' => 'منابع دریافت بسته',
            'updates' => 'به‌روزرسانی',
            'docker' => 'مدیریت Docker',
            'members' => 'اعضا و فضای کاری',
        ];
    }

    public static function allows(?array $sections, string $section, bool $owner): bool
    {
        if ($owner || $sections === null) {
            return true;
        }

        return in_array($section, $sections, true);
    }

    public static function routeSection(?string $name): ?string
    {
        if ($name === null || str_starts_with($name, 'settings.profile') || str_starts_with($name, 'settings.panel-port')) {
            return null;
        }

        return match (true) {
            $name === 'dashboard' => 'dashboard',
            str_starts_with($name, 'projects.') => 'projects',
            str_starts_with($name, 'git.') => 'git',
            str_starts_with($name, 'deployments.') => 'deployments',
            str_starts_with($name, 'servers.php') => 'php',
            str_starts_with($name, 'servers.') => 'servers',
            str_starts_with($name, 'catalog.') => 'catalog',
            str_starts_with($name, 'domains.') => 'domains',
            str_starts_with($name, 'databases.') => 'databases',
            str_starts_with($name, 'monitoring.') => 'monitoring',
            str_starts_with($name, 'jobs.') => 'jobs',
            str_starts_with($name, 'backups.') => 'backups',
            str_starts_with($name, 'audit.') => 'audit',
            str_starts_with($name, 'notifications.') => 'notifications',
            str_starts_with($name, 'mirrors.') => 'mirrors',
            str_starts_with($name, 'settings.updates') => 'updates',
            str_starts_with($name, 'docker.') => 'docker',
            $name === 'access.switch' => null,
            str_starts_with($name, 'access.') => 'members',
            default => null,
        };
    }
}
