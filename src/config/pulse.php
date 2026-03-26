<?php

use App\Recorders\RemoteServers;
use Laravel\Pulse\Recorders;

return [
    'enabled' => env('PULSE_ENABLED', true),

    'storage' => [
        'driver' => 'database',
        'drivers' => [
            'database' => [
                'connection' => env('PULSE_DB_CONNECTION', null),
                'chunk' => 1000,
            ],
        ],
    ],

    'ingest' => [
        'driver' => 'storage',
        'trim' => [
            'lottery' => [1, 1000],
            'keep' => '7 days',
        ],
    ],

    'recorders' => [
        // Local server metrics (the host running this container)
        Recorders\Servers::class => [
            'server_name' => env('PULSE_SERVER_NAME', gethostname()),
            'directories' => explode(':', env('PULSE_SERVER_DIRECTORIES', '/')),
        ],

        // Remote servers monitored via SSH (no PHP required on remote)
        // Configure in .docker/servers.php
        RemoteServers::class => require base_path('.docker/servers.php'),
    ],
];
