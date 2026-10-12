<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

use App\Infrastructure\Secrets\Exceptions\SecretFilePermissionException;
use RuntimeException;

final class SecretFile
{
    public static function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the secret directory.');
        }

        $temporary = $directory.'/.'.basename($path).'.'.bin2hex(random_bytes(6)).'.tmp';

        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the secret file.');
        }

        chmod($temporary, 0600);

        if (! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException('Unable to move the secret file into place.');
        }

        chmod($path, 0600);
        clearstatcache(true, $path);

        $mode = fileperms($path) & 0777;

        if (($mode & 0077) !== 0) {
            @unlink($path);

            throw new SecretFilePermissionException($path);
        }
    }
}
