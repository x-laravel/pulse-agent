<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PulseTest extends Command
{
    protected $signature = 'pulse:test';
    protected $description = 'Check database connectivity and remote server SSH access';

    public function handle(): void
    {
        $this->checkDatabase();
        $this->checkRemoteServers();
    }

    protected function checkDatabase(): void
    {
        $this->info('── Database ──────────────────────────────');

        try {
            DB::select('SELECT 1');
            $this->info('  Connection : OK (' . config('database.connections.mysql.host') . ')');
        } catch (\Throwable $e) {
            $this->error('  Connection : FAILED — ' . $e->getMessage());
            return;
        }

        try {
            $counts = [
                'pulse_values'     => DB::table('pulse_values')->count(),
                'pulse_entries'    => DB::table('pulse_entries')->count(),
                'pulse_aggregates' => DB::table('pulse_aggregates')->count(),
            ];
            foreach ($counts as $table => $count) {
                $this->line("  {$table}: {$count} rows");
            }
        } catch (\Throwable $e) {
            $this->error('  Tables     : ' . $e->getMessage());
        }
    }

    protected function checkRemoteServers(): void
    {
        $this->info('── Remote Servers ────────────────────────');

        $servers = config('pulse.recorders.' . \App\Recorders\RemoteServers::class . '.servers', []);

        if (empty($servers)) {
            $this->warn('  No remote servers configured in .docker/servers.php');
            return;
        }

        $scriptPath = base_path('app/Recorders/server-stats.sh');

        foreach ($servers as $server) {
            $name = $server['server_name'];
            $ssh  = $server['server_ssh'];
            $dirs = $server['directories'] ?? ['/'];

            $this->line("  [{$name}] {$ssh}");

            $dirArgs = implode(' ', array_map('escapeshellarg', $dirs));
            $raw = shell_exec("$ssh 'bash -s' $dirArgs < " . escapeshellarg($scriptPath) . ' 2>&1');

            if (empty($raw)) {
                $this->error("    SSH      : FAILED — no output");
                continue;
            }

            $lines = explode("\n", trim($raw));

            if (count($lines) < 3 + (count($dirs) * 2)) {
                $this->error("    SSH      : FAILED — unexpected output: " . implode(', ', $lines));
                continue;
            }

            $memTotalMb = (int) round((int) $lines[0] / 1024);
            $memUsedMb  = (int) round(((int) $lines[0] - (int) $lines[1]) / 1024);
            $cpu        = (int) $lines[2];

            $this->info("    SSH      : OK");
            $this->line("    CPU      : {$cpu}%");
            $this->line("    Memory   : {$memUsedMb} / {$memTotalMb} MB");

            $offset = 3;
            foreach ($dirs as $dir) {
                $usedMb  = (int) round((int) ($lines[$offset]     ?? 0) / 1024);
                $totalMb = (int) round((int) ($lines[$offset + 1] ?? 0) / 1024);
                $this->line("    Disk {$dir}: {$usedMb} / {$totalMb} MB");
                $offset += 2;
            }
        }
    }
}
