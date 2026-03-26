<?php

namespace App\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Support\Str;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;
use RuntimeException;

class RemoteServers
{
    public string $listen = SharedBeat::class;

    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
    ) {}

    public function record(SharedBeat $event): void
    {
        $servers = $this->config->get('pulse.recorders.' . static::class . '.servers', []);

        foreach ($servers as $server) {
            $this->recordServer($event, $server);
        }
    }

    protected function recordServer(SharedBeat $event, array $server): void
    {
        $queryInterval = (int) ($server['query_interval'] ?? 15);

        if ($event->time->second % $queryInterval !== 0) {
            return;
        }

        $ssh         = $server['server_ssh'];
        $name        = $server['server_name'];
        $directories = $server['directories'] ?? ['/'];
        $slug        = Str::slug($name);
        $scriptPath  = __DIR__ . '/server-stats.sh';

        $dirArgs = implode(' ', array_map('escapeshellarg', $directories));

        $raw = shell_exec("$ssh 'bash -s' $dirArgs < " . escapeshellarg($scriptPath) . ' 2>/dev/null');

        if (empty($raw)) {
            return;
        }

        $parsed = $this->parseStats($raw, $directories);

        if ($parsed === null) {
            return;
        }

        $this->pulse->record('cpu', $slug, $parsed['cpu'], $event->time)->avg()->onlyBuckets();
        $this->pulse->record('memory', $slug, $parsed['memory_used'], $event->time)->avg()->onlyBuckets();
        $this->pulse->set('system', $slug, json_encode([
            'name'         => $name,
            'cpu'          => $parsed['cpu'],
            'memory_used'  => $parsed['memory_used'],
            'memory_total' => $parsed['memory_total'],
            'storage'      => $parsed['storage'],
        ], JSON_THROW_ON_ERROR), $event->time);
    }

    public function parseStats(string $raw, array $directories): ?array
    {
        $lines = explode("\n", trim($raw));

        if (count($lines) < 3 + (count($directories) * 2)) {
            return null;
        }

        $memTotalKb     = (int) $lines[0];
        $memAvailableKb = (int) $lines[1];
        $cpu            = (int) $lines[2];

        $storage = [];
        $offset  = 3;

        foreach ($directories as $directory) {
            $usedKb  = (int) ($lines[$offset]     ?? 0);
            $totalKb = (int) ($lines[$offset + 1] ?? 0);

            $storage[] = [
                'directory' => $directory,
                'total'     => (int) round($totalKb / 1024), // MB
                'used'      => (int) round($usedKb  / 1024), // MB
            ];

            $offset += 2;
        }

        return [
            'cpu'          => $cpu,
            'memory_total' => (int) round($memTotalKb / 1024),                        // MB
            'memory_used'  => (int) round(($memTotalKb - $memAvailableKb) / 1024),    // MB
            'storage'      => $storage,
        ];
    }
}
