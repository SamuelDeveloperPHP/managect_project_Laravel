<?php

return [
    'source' => [
        'host' => env('PHALCON_SOURCE_HOST', '127.0.0.1'),
        'port' => (int) env('PHALCON_SOURCE_PORT', 3307),
        'database' => env('PHALCON_SOURCE_DATABASE', 'phalcon'),
        'username' => env('PHALCON_SOURCE_USERNAME', 'phalcon'),
        'password' => env('PHALCON_SOURCE_PASSWORD', env('DB_LEGACY_PASSWORD', '')),
    ],
];
