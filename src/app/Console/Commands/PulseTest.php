<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PulseTest extends Command
{
    protected $signature = 'pulse:test';
    protected $description = 'Test DB connection and pulse_values table';

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

        // pulse_values table
        try {
            $count = DB::table('pulse_values')->where('type', 'system')->count();
            $this->info("pulse_values (type=system): {$count} record(s)");
        } catch (\Throwable $e) {
            $this->error('pulse_values: ' . $e->getMessage());
        }

        // pulse_aggregates table
        try {
            $count = DB::table('pulse_aggregates')->whereIn('type', ['cpu', 'memory'])->count();
            $this->info("pulse_aggregates (cpu/memory): {$count} record(s)");
        } catch (\Throwable $e) {
            $this->error('pulse_aggregates: ' . $e->getMessage());
        }
    }
}
