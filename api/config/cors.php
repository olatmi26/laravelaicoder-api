<?php

return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        'http://localhost:3000',
        'http://localhost:3001',
        'app://.',           // Electron production
        'http://localhost',  // Electron dev
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => ['Content-Type', 'X-Requested-With'],
    'max_age'                  => 0,
    'supports_credentials'     => true,
];
