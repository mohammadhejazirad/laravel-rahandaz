<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || ! str_starts_with((string) config('database.connections.pgsql.database'), 'rahandaz_v1_ui_')) {
    throw new RuntimeException('The browser server requires an isolated E2E testing database.');
}
$public = realpath(__DIR__.'/../../public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = is_string($path) ? realpath($public.'/'.rawurldecode($path)) : false;
if ($file !== false && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
$disabledHotFile = __DIR__.'/disabled.hot';
if (file_exists($disabledHotFile)) {
    throw new RuntimeException('E2E must not load a development hot file.');
}
Vite::useHotFile($disabledHotFile);
$app->handleRequest(Request::capture());
