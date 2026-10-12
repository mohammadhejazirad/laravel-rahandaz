<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$directory = '/tmp/rahandaz-phpunit';
$key = $directory.'/master.key';
$canary = $directory.'/canary.json';

if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    throw new RuntimeException('Unable to create the PHPUnit secret directory.');
}

$mode = is_file($key) ? (fileperms($key) & 0777) : false;

if (! is_file($key) || filesize($key) === 0 || $mode !== 0600) {
    file_put_contents($key, base64_encode(random_bytes(32))."\n");
    chmod($key, 0600);
    if (is_file($canary)) {
        unlink($canary);
    }
}

putenv('RAHANDAZ_MASTER_KEY_FILE='.$key);
putenv('RAHANDAZ_CANARY_FILE='.$canary);
$_ENV['RAHANDAZ_MASTER_KEY_FILE'] = $key;
$_ENV['RAHANDAZ_CANARY_FILE'] = $canary;
$_SERVER['RAHANDAZ_MASTER_KEY_FILE'] = $key;
$_SERVER['RAHANDAZ_CANARY_FILE'] = $canary;
