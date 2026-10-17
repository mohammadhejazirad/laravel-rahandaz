<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:' || config('session.driver') !== 'array') {
    throw new RuntimeException('PWA browser tests require testing, in-memory SQLite and array sessions.');
}
$public = realpath(__DIR__.'/../../public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = is_string($path) ? realpath($public.'/'.rawurldecode($path)) : false;
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
Vite::useHotFile('/tmp/rahandaz-pwa-disabled.hot');
$request = Request::capture();
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
// Test-only deployment revision: no production source or artifact is modified.
$revision = getenv('RAHANDAZ_PWA_TEST_REVISION');
if ($path === '/rahandaz/service-worker.js' && is_string($revision) && str_starts_with($revision, '/tmp/') && is_file($revision)) {
    $response->setContent(preg_replace('/const VERSION = "[a-f0-9]+";/', 'const VERSION = "'.hash_file('sha256', $revision).'";', $response->getContent()));
}
$response->send();
$kernel->terminate($request, $response);
