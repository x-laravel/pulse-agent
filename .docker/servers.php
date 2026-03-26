<?php

/**
 * Remote servers to monitor via SSH.
 *
 * Each server needs SSH key-based auth from this container.
 * SSH private key must be mounted at /root/.ssh/id_rsa
 *
 * To add a server's host key to known_hosts:
 *   docker compose exec pulse-agent ssh-keyscan <host> >> /root/.ssh/known_hosts
 */

return [
    'servers' => [
        // [
        //     'server_name'    => 'db-server',
        //     'server_ssh'     => 'ssh deploy@192.168.1.10',
        //     'query_interval' => 15,
        //     'directories'    => ['/'],
        // ],
        // [
        //     'server_name'    => 'worker-01',
        //     'server_ssh'     => 'ssh deploy@192.168.1.11 -p 2222',
        //     'query_interval' => 15,
        //     'directories'    => ['/', '/data'],
        // ],
    ],
];
