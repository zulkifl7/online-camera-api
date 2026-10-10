<?php

$frontend = env('FRONTEND_URL', 'http://localhost:3000');

return [

    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter([
        $frontend,
        'http://localhost:3000',
    ]))),

    'allowed_origins_patterns' => [
        '#^http://192\.168\.\d{1,3}\.\d{1,3}\.\d{1,3}:3000$#',
        '#^http://10\.\d{1,3}\.\d{1,3}\.\d{1,3}:3000$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
