<?php

return [
    'name' => 'Pulse Agent',
    'env' => env('APP_ENV', 'production'),
    'debug' => false,
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => 'en',
];
