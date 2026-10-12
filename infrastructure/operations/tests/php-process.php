<?php

declare(strict_types=1);

use App\Infrastructure\Operations\LocalOperationProcess;
use App\Infrastructure\Operations\OperationCatalog;
use App\Infrastructure\Operations\OperationInputGuard;
use App\Infrastructure\Operations\OperationResultParser;
use App\Infrastructure\Secrets\Redactor;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';

$executionId = '018f3a2e-7c3a-7b2a-8c1d-123456789abc';
$directory = sys_get_temp_dir().'/rh-os-'.bin2hex(random_bytes(4));
mkdir($directory);
file_put_contents($directory.'/os-release', "NAME=\"Ubuntu\"\nID=ubuntu\nVERSION_ID=\"24.04\"\nVERSION_CODENAME=noble\n");

$catalog = new OperationCatalog($root.'/infrastructure/operations/contracts/registry.json');
$process = new LocalOperationProcess($catalog, new OperationInputGuard, new OperationResultParser(new Redactor));

try {
    $result = $process->run('host.facts', [
        'execution_id' => $executionId,
    ], [
        'RAHANDAZ_OPS_OS_RELEASE' => $directory.'/os-release',
    ]);
} finally {
    @unlink($directory.'/os-release');
    @rmdir($directory);
}

$failed = $result->status !== 'succeeded'
    || ($result->observedState['compatibility'] ?? null) !== 'untested'
    || ($result->observedState['recognized'] ?? null) !== true
    || $result->mutated
    || $result->errorCode !== null;

fwrite(STDOUT, $failed ? "not ok php process\n" : "ok php process\n");
exit($failed ? 1 : 0);
