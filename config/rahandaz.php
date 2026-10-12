<?php

$basePath = '/'.trim((string) env('RAHANDAZ_BASE_PATH', '/rahandaz'), '/');

return [

    /*
    | Host mutation stays off until a versioned helper and a disposable VM
    | test exist. "local" still does not run commands in this slice.
    */
    'execution_mode' => env('RAHANDAZ_EXECUTION_MODE', 'disabled'),

    'base_path' => $basePath === '/' ? '/rahandaz' : $basePath,

    'master_key_file' => env('RAHANDAZ_MASTER_KEY_FILE', base_path('var/config/master.key')),

    'canary_file' => env('RAHANDAZ_CANARY_FILE', base_path('var/config/canary.json')),

    /*
    | retry_after on the Redis queue connection must stay greater than this.
    */
    'worker_timeout' => (int) env('RAHANDAZ_WORKER_TIMEOUT', 60),

    'default_timezone' => env('RAHANDAZ_DEFAULT_TIMEZONE', 'Asia/Tehran'),

];
