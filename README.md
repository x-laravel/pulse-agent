# pulse-agent

A lightweight Docker container that collects server metrics and writes them directly to your [Laravel Pulse](https://pulse.laravel.com) database — no changes needed to your existing Laravel app.

## How it works

```
┌─────────────────────────────────────────────────────────────────┐
│  pulse-agent container                                          │
│                                                                 │
│  php artisan pulse:check                                        │
│  ├── Servers recorder    → local CPU / memory / disk            │
│  └── RemoteServers recorder → SSH → remote servers              │
│                               └── server-stats.sh              │
│                                                                 │
│  Every 15s: writes to ──────────────────────► MySQL (pulse_*)   │
└─────────────────────────────────────────────────────────────────┘
                                                       ▲
                                            Laravel app reads here
                                            → Pulse dashboard
```

The agent shares the same MySQL database as your Laravel app. No additional infrastructure required.

## Requirements

- Docker & Docker Compose
- MySQL database with Pulse tables (see [pulse-oci-mysql](https://github.com/x-laravel/pulse-oci-mysql) for Oracle Cloud MySQL)
- SSH key-based access to any remote servers you want to monitor

## Setup

### 1. Configure environment

```bash
cp .env.example .env
```

Edit `.env`:

```env
APP_KEY=base64:...            # Generate with: php artisan key:generate --show
PULSE_SERVER_NAME=my-server   # Name shown in the Pulse dashboard
PULSE_SERVER_DIRECTORIES=/    # Colon-separated list of disk paths to monitor

DB_HOST=your-mysql-host
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=your-user
DB_PASSWORD=your-password
```

> `APP_KEY` must be the same across all servers writing to the same Pulse database.

### 2. Add SSH keys (optional — for remote server monitoring)

Place your private key in `.docker/ssh/`:

```bash
cp ~/.ssh/id_rsa .docker/ssh/id_rsa
chmod 600 .docker/ssh/id_rsa
```

Add the remote server's host key to `known_hosts`:

```bash
docker compose run --rm pulse-agent ssh-keyscan <host> >> .docker/ssh/known_hosts
```

### 3. Configure remote servers (optional)

Edit `.docker/servers.php`:

```php
return [
    'servers' => [
        [
            'server_name'    => 'api-server',
            'server_ssh'     => 'ssh deploy@10.0.0.5',
            'query_interval' => 15,   // seconds
            'directories'    => ['/'],
        ],
    ],
];
```

### 4. Run

```bash
docker compose up -d
```

## Testing

```bash
docker compose exec pulse-agent php artisan pulse:test
```

```
  Database  your-mysql-host
  ──────────────────────────────────────────────────
  ✓ Connection established
  │ pulse_values           12 rows
  │ pulse_entries       4,820 rows
  │ pulse_aggregates      384 rows

  Remote Servers
  ──────────────────────────────────────────────────

  api-server  ssh deploy@10.0.0.5
  ✓ SSH connected
  │ CPU     14%
  │ Memory  1,823 / 3,840 MB
  │ Disk /  18,432 / 51,200 MB
```

## Project structure

```
pulse-agent/
├── .docker/
│   ├── servers.php       ← Remote server list (mounted as volume)
│   └── ssh/              ← SSH keys (mounted as volume, not committed)
├── src/
│   ├── app/
│   │   ├── Console/Commands/PulseTest.php
│   │   └── Recorders/
│   │       ├── RemoteServers.php   ← SSH-based remote metrics recorder
│   │       └── server-stats.sh    ← Bash script piped to remote servers
│   └── config/
│       └── pulse.php
├── .env.example
├── docker-compose.yml
└── Dockerfile
```

## Oracle Cloud MySQL

If you're using Oracle Cloud MySQL (OCI), the default Pulse migration uses generated columns which OCI does not support. Use [x-laravel/pulse-oci-mysql](https://packagist.org/packages/x-laravel/pulse-oci-mysql) instead of running Pulse's own migrations.

## License

MIT
