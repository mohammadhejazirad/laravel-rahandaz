<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ClearLogCommandTest extends TestCase
{
    public function test_log_clear_empties_the_file_and_keeps_it(): void
    {
        $directory = storage_path('logs');
        $name = 'log-clear-'.bin2hex(random_bytes(4));
        $path = $directory.DIRECTORY_SEPARATOR.$name.'.log';
        $dated = $directory.DIRECTORY_SEPARATOR.$name.'-2026-10-19.log';
        $other = $directory.DIRECTORY_SEPARATOR.$name.'-notes.log';
        file_put_contents($path, "first line\n");
        file_put_contents($dated, "old day\n");
        file_put_contents($other, "keep\n");
        $mode = fileperms($path);
        config([
            'logging.channels.single.path' => $path,
            'logging.channels.daily.path' => $path,
            'logging.channels.monthly.path' => $path,
            'logging.channels.emergency.path' => $path,
        ]);

        try {
            $this->artisan('log:clear')->assertSuccessful();

            $this->assertFileExists($path);
            $this->assertSame('', (string) file_get_contents($path));
            $this->assertFileExists($dated);
            $this->assertSame('', (string) file_get_contents($dated));
            $this->assertSame("keep\n", (string) file_get_contents($other));
            $this->assertSame($mode, fileperms($path));
        } finally {
            @unlink($path);
            @unlink($dated);
            @unlink($other);
        }
    }

    public function test_log_clear_creates_a_missing_file_empty(): void
    {
        $path = storage_path('logs/log-clear-missing-'.bin2hex(random_bytes(4)).'.log');
        $this->assertFileDoesNotExist($path);
        config([
            'logging.channels.single.path' => $path,
            'logging.channels.daily.path' => $path,
            'logging.channels.monthly.path' => $path,
            'logging.channels.emergency.path' => $path,
        ]);

        try {
            $this->artisan('log:clear')->assertSuccessful();
            $this->assertFileExists($path);
            $this->assertSame('', (string) file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_log_clear_refuses_a_symlink_and_a_path_outside_the_log_directory(): void
    {
        $directory = storage_path('logs');
        $target = $directory.DIRECTORY_SEPARATOR.'log-clear-target-'.bin2hex(random_bytes(4)).'.log';
        $link = $directory.DIRECTORY_SEPARATOR.'log-clear-link-'.bin2hex(random_bytes(4)).'.log';
        $outside = sys_get_temp_dir().'/rahandaz-log-clear-'.bin2hex(random_bytes(4)).'.log';
        file_put_contents($target, "stay\n");
        symlink($target, $link);

        try {
            config([
                'logging.channels.single.path' => $link,
                'logging.channels.daily.path' => $link,
                'logging.channels.monthly.path' => $link,
                'logging.channels.emergency.path' => $link,
            ]);
            $this->artisan('log:clear')->assertFailed();
            $this->assertSame("stay\n", (string) file_get_contents($target));

            config([
                'logging.channels.single.path' => $outside,
                'logging.channels.daily.path' => $outside,
                'logging.channels.monthly.path' => $outside,
                'logging.channels.emergency.path' => $outside,
            ]);
            $this->artisan('log:clear')->assertFailed();
            $this->assertFileDoesNotExist($outside);
        } finally {
            @unlink($link);
            @unlink($target);
            @unlink($outside);
        }
    }
}
