<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PulseTest extends Command
{
    protected $signature = 'pulse:test {--trust : Add remote server host keys to known_hosts automatically}';
    protected $description = 'Check database connectivity and remote server SSH access';

    public function handle(): void
    {
        $this->newLine();
        $this->checkDatabase();
        $this->newLine();
        $this->checkRemoteServers();
        $this->newLine();
    }

    protected function extractHost(string $sshCommand): string
    {
        // Handles: ssh user@host, ssh user@host -p 2222, ssh -p 2222 user@host
        if (preg_match('/\w+@(\S+)/', $sshCommand, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function checkDatabase(): void
    {
        $host = config('database.connections.mysql.host');
        $this->line("  <fg=cyan;options=bold>Database</> <fg=gray>{$host}</>");
        $this->line('  ' . str_repeat('─', 50));

        try {
            DB::select('SELECT 1');
            $this->line('  <fg=green>✓</> Connection established');
        } catch (\Throwable $e) {
            $this->line('  <fg=red>✗</> Connection failed: ' . $e->getMessage());
            return;
        }

        try {
            $tables = ['pulse_values', 'pulse_entries', 'pulse_aggregates'];
            foreach ($tables as $table) {
                $count = DB::table($table)->count();
                $this->line(sprintf('  <fg=gray>│</> %-22s %s rows', $table, number_format($count)));
            }
        } catch (\Throwable $e) {
            $this->line('  <fg=red>✗</> Tables: ' . $e->getMessage());
        }
    }

    protected function checkRemoteServers(): void
    {
        $this->line('  <fg=cyan;options=bold>Remote Servers</>');
        $this->line('  ' . str_repeat('─', 50));

        $servers = config('pulse.recorders.' . \App\Recorders\RemoteServers::class . '.servers', []);

        if (empty($servers)) {
            $this->line('  <fg=yellow>⚠</> No servers configured in .docker/servers.php');
            return;
        }

        $scriptPath = base_path('app/Recorders/server-stats.sh');

        foreach ($servers as $server) {
            $name = $server['server_name'];
            $ssh  = $server['server_ssh'];
            $dirs = $server['directories'] ?? ['/'];

            $this->newLine();
            $this->line("  <fg=white;options=bold>{$name}</> <fg=gray>{$ssh}</>");

            if ($this->option('trust')) {
                $host = $this->extractHost($ssh);
                shell_exec("ssh-keyscan -H {$host} >> /root/.ssh/known_hosts 2>/dev/null");
                $this->line('  <fg=gray>│</> Host key added to known_hosts');
            }

            $dirArgs = implode(' ', array_map('escapeshellarg', $dirs));
            $raw = shell_exec("$ssh 'bash -s' $dirArgs < " . escapeshellarg($scriptPath) . ' 2>&1');

            if (empty($raw)) {
                $this->line('  <fg=red>✗</> SSH failed — no output');
                continue;
            }

            $lines = explode("\n", trim($raw));

            if (count($lines) < 3 + (count($dirs) * 2)) {
                $this->line('  <fg=red>✗</> SSH failed — unexpected output: ' . implode(' ', $lines));
                continue;
            }

            $memTotalMb = (int) round((int) $lines[0] / 1024);
            $memUsedMb  = (int) round(((int) $lines[0] - (int) $lines[1]) / 1024);
            $cpu        = (int) $lines[2];

            $this->line("  <fg=green>✓</> SSH connected");
            $this->line(sprintf('  <fg=gray>│</> CPU     %d%%', $cpu));
            $this->line(sprintf('  <fg=gray>│</> Memory  %s / %s MB', number_format($memUsedMb), number_format($memTotalMb)));

            $offset = 3;
            foreach ($dirs as $dir) {
                $usedMb  = (int) round((int) ($lines[$offset]     ?? 0) / 1024);
                $totalMb = (int) round((int) ($lines[$offset + 1] ?? 0) / 1024);
                $this->line(sprintf('  <fg=gray>│</> Disk %-4s %s / %s MB', $dir, number_format($usedMb), number_format($totalMb)));
                $offset += 2;
            }
        }
    }
}
