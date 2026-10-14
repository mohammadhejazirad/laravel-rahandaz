<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Secrets\KeyCanary;
use App\Infrastructure\Secrets\MasterKey;
use App\Infrastructure\Updates\PhpRuntime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class DoctorCommand extends Command
{
    protected $signature = 'rahandaz:doctor';

    protected $description = 'Check control-plane dependencies and fail closed when a secret is unusable';

    public function handle(KeyCanary $canary): int
    {
        $failed = false;

        $failed = ! $this->report('app_key', $this->appKeyIsPresent()) || $failed;
        $failed = ! $this->report('extension_zip', extension_loaded('zip')) || $failed;
        $failed = ! $this->report('extension_fileinfo', extension_loaded('fileinfo')) || $failed;
        $failed = ! $this->report('master_key', $this->masterKeyIsUsable()) || $failed;
        $failed = ! $this->report('canary', $canary->verify((string) config('rahandaz.canary_file'))) || $failed;
        $failed = ! $this->report('database', $this->databaseResponds()) || $failed;
        $failed = ! $this->report('redis_queue', $this->redisResponds('queue')) || $failed;
        $failed = ! $this->report('redis_cache', $this->redisResponds('cache')) || $failed;
        $failed = ! $this->report('queue_timing', $this->queueTimingIsSafe()) || $failed;

        $mode = (string) config('rahandaz.execution_mode');
        $modeOk = in_array($mode, ['disabled', 'local'], true);
        $failed = ! $this->report('execution_mode', $modeOk, $mode) || $failed;

        $phpOk = PhpRuntime::isRequiredSeries();
        if (app()->environment('production')) {
            $failed = ! $this->report('php_runtime', $phpOk, PHP_VERSION) || $failed;
        } else {
            $this->line('php_runtime: '.($phpOk ? 'ok' : 'warn').' '.PHP_VERSION);
        }

        $masterKeyPath = (string) config('rahandaz.master_key_file');
        $masterKeyMode = is_file($masterKeyPath) ? fileperms($masterKeyPath) : false;

        if ($masterKeyMode === false || ($masterKeyMode & 0077) !== 0) {
            $this->line('master_key_mode: fail');
            $failed = true;
        } else {
            $this->line('master_key_mode: ok');
        }

        if (app()->environment('production') && config('session.secure') !== true) {
            $this->line('secure_cookie: warn');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function report(string $check, bool $ok, ?string $detail = null): bool
    {
        $suffix = $detail === null ? '' : ' '.$detail;
        $this->line($check.': '.($ok ? 'ok' : 'fail').$suffix);

        return $ok;
    }

    private function appKeyIsPresent(): bool
    {
        $key = (string) config('app.key');

        return $key !== '' && ! str_contains($key, 'SomeRandomString');
    }

    private function masterKeyIsUsable(): bool
    {
        try {
            $bytes = MasterKey::fromConfig()->bytes();

            return strlen($bytes) === 32;
        } catch (Throwable) {
            return false;
        }
    }

    private function databaseResponds(): bool
    {
        try {
            $row = DB::select('select 1 as ok');

            return isset($row[0]->ok);
        } catch (Throwable) {
            return false;
        }
    }

    private function redisResponds(string $connection): bool
    {
        try {
            $pong = Redis::connection($connection)->ping();

            if ($pong === true) {
                return true;
            }

            return is_string($pong) && str_contains(strtoupper($pong), 'PONG');
        } catch (Throwable) {
            return false;
        }
    }

    private function queueTimingIsSafe(): bool
    {
        return (int) config('queue.connections.redis.retry_after') > (int) config('rahandaz.worker_timeout');
    }
}
