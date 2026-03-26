<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Facades\Pulse;

class PulseTest extends Command
{
    protected $signature = 'pulse:test';
    protected $description = 'Test DB connection, manually fire SharedBeat and check if recorder writes data';

    public function handle(): void
    {
        // DB connection
        try {
            DB::select('SELECT 1');
            $this->info('DB connection: OK');
        } catch (\Throwable $e) {
            $this->error('DB connection: FAILED — ' . $e->getMessage());
            return;
        }

        // Manually fire SharedBeat with second=0 (triggers Servers recorder: 0 % 15 === 0)
        $this->info('Firing SharedBeat...');
        try {
            $time = \Carbon\CarbonImmutable::now()->setSecond(0);
            event(new SharedBeat($time, 'test'));
            $this->info('SharedBeat fired: OK');
        } catch (\Throwable $e) {
            $this->error('SharedBeat: FAILED — ' . $e->getMessage());
            return;
        }

        // Ingest buffered data into DB
        $this->info('Ingesting...');
        try {
            Pulse::ingest();
            $this->info('Ingest: OK');
        } catch (\Throwable $e) {
            $this->error('Ingest: FAILED — ' . $e->getMessage());
            return;
        }

        // Check result
        $system = DB::table('pulse_values')->where('type', 'system')->get(['key', 'value']);
        $this->info("pulse_values (type=system): {$system->count()} record(s)");
        foreach ($system as $row) {
            $this->line("  key={$row->key} value={$row->value}");
        }
    }
}
