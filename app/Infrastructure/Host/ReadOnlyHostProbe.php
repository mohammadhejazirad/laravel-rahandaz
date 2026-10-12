<?php

declare(strict_types=1);

namespace App\Infrastructure\Host;

final class ReadOnlyHostProbe
{
    public function __construct(
        private readonly HostFileReader $files,
        private readonly OsReleaseParser $osRelease,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $osFile = $this->files->read('/etc/os-release');
        $memoryFile = $this->files->read('/proc/meminfo');
        $free = @disk_free_space('/');
        $total = @disk_total_space('/');

        return [
            'observed_at' => now()->utc()->toIso8601String(),
            'compatibility' => 'untested',
            'compatibility_reason' => 'Ubuntu support is not claimed until the VM matrix has run.',
            'hostname' => php_uname('n'),
            'kernel' => php_uname('r'),
            'architecture' => php_uname('m'),
            'php' => PHP_VERSION,
            'os' => $osFile === null ? null : $this->osRelease->parse($osFile),
            'memory' => $this->memory($memoryFile),
            'disk' => [
                'path' => '/',
                'free_bytes' => $free === false ? null : (int) $free,
                'total_bytes' => $total === false ? null : (int) $total,
            ],
            'execution_mode' => (string) config('rahandaz.execution_mode'),
        ];
    }

    /**
     * @return array{total_kib: ?int, available_kib: ?int}
     */
    private function memory(?string $meminfo): array
    {
        if ($meminfo === null) {
            return ['total_kib' => null, 'available_kib' => null];
        }

        $total = null;
        $available = null;

        foreach (preg_split("/\r\n|\n|\r/", $meminfo) ?: [] as $line) {
            if (preg_match('/^(MemTotal|MemAvailable):\s+(\d+)/', $line, $matches) !== 1) {
                continue;
            }

            $kib = (int) $matches[2];

            if ($matches[1] === 'MemTotal') {
                $total = $kib;
            }

            if ($matches[1] === 'MemAvailable') {
                $available = $kib;
            }
        }

        return ['total_kib' => $total, 'available_kib' => $available];
    }
}
