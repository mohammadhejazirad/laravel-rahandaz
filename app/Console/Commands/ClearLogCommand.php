<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearLogCommand extends Command
{
    protected $signature = 'log:clear';

    protected $description = 'Empty the application log file without deleting it';

    public function handle(): int
    {
        $directory = realpath(storage_path('logs'));
        if ($directory === false || ! is_dir($directory)) {
            $this->error('Log directory is missing.');

            return self::FAILURE;
        }

        $paths = $this->targets($directory);
        if ($paths === []) {
            $this->error('No application log file is configured inside storage/logs.');

            return self::FAILURE;
        }

        foreach ($paths as $path) {
            if (! $this->emptyFile($path, $directory)) {
                return self::FAILURE;
            }
            $this->components->info('Emptied '.$path);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function targets(string $directory): array
    {
        $files = [];
        foreach (['single', 'daily', 'monthly', 'emergency'] as $channel) {
            $configured = config('logging.channels.'.$channel.'.path');
            if (! is_string($configured) || $configured === '') {
                continue;
            }
            if (realpath(dirname($configured)) !== $directory) {
                continue;
            }
            $base = pathinfo($configured, PATHINFO_FILENAME);
            $extension = pathinfo($configured, PATHINFO_EXTENSION);
            if ($extension !== 'log' || preg_match('/^[A-Za-z0-9._-]+$/', $base) !== 1) {
                continue;
            }
            $files[$directory.DIRECTORY_SEPARATOR.$base.'.log'] = true;
            foreach (glob($directory.DIRECTORY_SEPARATOR.$base.'-*.log') ?: [] as $candidate) {
                if (preg_match('/^'.preg_quote($base, '/').'-\d{4}-\d{2}-\d{2}\.log$/', basename($candidate)) === 1) {
                    $files[$candidate] = true;
                }
            }
        }

        $paths = array_keys($files);
        sort($paths);

        return $paths;
    }

    private function emptyFile(string $path, string $directory): bool
    {
        if (is_link($path)) {
            $this->error('Refusing to clear a symlink: '.$path);

            return false;
        }

        if (is_dir($path)) {
            $this->error('Refusing to clear a directory: '.$path);

            return false;
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            $this->error('Could not open the log file: '.$path);

            return false;
        }

        $real = realpath($path);
        if ($real === false || dirname($real) !== $directory || ! is_file($real)) {
            fclose($handle);
            $this->error('Refusing a log path outside storage/logs.');

            return false;
        }

        $emptied = flock($handle, LOCK_EX) && ftruncate($handle, 0) && fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if (! $emptied || ! is_file($path)) {
            $this->error('Could not empty the log file: '.$path);

            return false;
        }

        return true;
    }
}
