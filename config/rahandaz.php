<?php

$basePath = '/'.trim((string) env('RAHANDAZ_BASE_PATH', '/rahandaz'), '/');

return [

    /*
    | Host mutation stays off until a versioned helper and a disposable VM
    | test exist. "local" still does not run commands in this slice.
    */
    'execution_mode' => env('RAHANDAZ_EXECUTION_MODE', 'disabled'),

    'php_series' => '8.4',

    'release_file' => env('RAHANDAZ_RELEASE_FILE', ''),

    'update_channel' => env('RAHANDAZ_UPDATE_CHANNEL', 'stable'),

    'github_repository' => env('RAHANDAZ_GITHUB_REPOSITORY', ''),

    'base_path' => $basePath === '/' ? '/rahandaz' : $basePath,

    'master_key_file' => env('RAHANDAZ_MASTER_KEY_FILE', base_path('var/config/master.key')),

    'canary_file' => env('RAHANDAZ_CANARY_FILE', base_path('var/config/canary.json')),

    /*
    | retry_after on the Redis queue connection must stay greater than this.
    */
    'worker_timeout' => (int) env('RAHANDAZ_WORKER_TIMEOUT', 60),

    'default_timezone' => env('RAHANDAZ_DEFAULT_TIMEZONE', 'Asia/Tehran'),

    'location' => [
        'enabled' => filter_var(env('RAHANDAZ_LOCATION_LOOKUP', true), FILTER_VALIDATE_BOOL),
        'cache_seconds' => (int) env('RAHANDAZ_LOCATION_CACHE_SECONDS', 21600),
        'failure_cache_seconds' => (int) env('RAHANDAZ_LOCATION_FAILURE_CACHE_SECONDS', 600),
    ],

    'project_archives' => [
        'disk' => 'project_archives',
        'max_upload_bytes' => (int) env('RAHANDAZ_ARCHIVE_MAX_UPLOAD_BYTES', 104857600),
        'max_entries' => (int) env('RAHANDAZ_ARCHIVE_MAX_ENTRIES', 20000),
        'max_entry_bytes' => (int) env('RAHANDAZ_ARCHIVE_MAX_ENTRY_BYTES', 268435456),
        'max_uncompressed_bytes' => (int) env('RAHANDAZ_ARCHIVE_MAX_UNCOMPRESSED_BYTES', 1073741824),
        'max_compression_ratio' => (float) env('RAHANDAZ_ARCHIVE_MAX_COMPRESSION_RATIO', 100),
    ],

];
