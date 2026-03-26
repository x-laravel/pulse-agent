# pulse-agent

## Overview
A minimal standalone Laravel application that collects server metrics and pushes them to a Laravel Pulse database. Monitors the local host and any number of remote servers over SSH — no PHP installation required on remote machines.

- **Package name:** `x-laravel/pulse-agent`
- **Location:** `~/Projects/x-laravel/pulse-agent`

## Requirements
- PHP ^8.4
- Laravel ^12.0
- Laravel Pulse ^1.0
- x-laravel/pulse-oci-mysql ^1.0
- PHPUnit ^11.0|^12.0 (dev)

## Source Files (`src/app/`)

| File | Type | Responsibility |
|------|------|----------------|
| `Recorders/RemoteServers.php` | recorder | Listens to `SharedBeat`. SSHes into each configured server, runs `server-stats.sh`, parses the output and records CPU, memory, and disk metrics into Pulse. |
| `Recorders/server-stats.sh` | shell script | Runs on the remote server via `bash -s`. Outputs mem total (KB), mem available (KB), CPU %, then used/total KB pairs for each requested directory. |
| `Console/Commands/PulseTest.php` | command | `php artisan pulse:test [--trust]`. Verifies DB connectivity and SSH access to all configured servers. Prints a human-readable report. `--trust` appends remote host keys to `known_hosts`. |

## Configuration

Remote servers are configured via `.docker/servers.php` (mounted into the container at runtime):

```php
return [
    'servers' => [
        [
            'server_name' => 'web-01',
            'server_ssh'  => 'ssh deployer@web-01.example.com',
            'directories' => ['/', '/var/www'],
            'query_interval' => 15, // seconds
        ],
    ],
];
```

## Key Design Decisions

### `parseStats()` — extracted for testability
SSH output parsing is isolated in a `public parseStats(string $raw, array $directories): ?array` method on `RemoteServers`. This keeps `recordServer()` thin and allows unit tests to cover the parsing logic without mocking `shell_exec`.

### No PHP on remote servers
`server-stats.sh` is streamed over SSH via `bash -s`. The remote host only needs a POSIX shell, `cat /proc/meminfo`, `vmstat`, and `df`.

### `SharedBeat` — not `IsolatedBeat`
The recorder uses `SharedBeat` so all remote server checks are driven by the same heartbeat, respecting `query_interval` per server.

## Git Commits

Never create a commit unless the user explicitly requests it. Always wait for a clear instruction before running `git commit`.

## Running Tests

```bash
cd src
composer install
vendor/bin/phpunit
```

Manual connectivity check (requires a real `.env`, `servers.php`, and SSH keys):

```bash
docker compose --profile test run --rm pulse-test
```

## CI/CD
`.github/workflows/tests.yml` runs PHPUnit on PHP 8.4 and 8.5 against `ubuntu-latest`. No database or SSH required — unit tests cover only the parsing logic.
