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

        // Check if Servers recorder is actually invoked
        $recorderCalled = false;
        \Laravel\Pulse\Recorders\Servers::detectCpuUsing(function () use (&$recorderCalled) {
            $recorderCalled = true;
            return 0;
        });

        // Call recorder directly (bypasses Pulse::rescue() so exceptions surface)
        $this->info('Calling recorder directly...');
        $time = \Carbon\CarbonImmutable::now()->setSecond(0);
        try {
            $recorder = app(\Laravel\Pulse\Recorders\Servers::class);
            $recorder->record(new SharedBeat($time, 'test'));
            $this->info('Recorder: OK');
        } catch (\Throwable $e) {
            $this->error('Recorder error: ' . $e->getMessage());
            $this->error('  at ' . $e->getFile() . ':' . $e->getLine());
            return;
        }

        $this->info('Servers recorder invoked: ' . ($recorderCalled ? 'YES' : 'NO'));

        // Check Pulse buffer directly
        $pulse = app(\Laravel\Pulse\Pulse::class);
        $ref = new \ReflectionClass($pulse);
        $prop = $ref->getProperty('entries');
        $prop->setAccessible(true);
        $entries = $prop->getValue($pulse);
        $this->info('Pulse buffer count: ' . count($entries));

        // Verify storage binding
        $storage = app(\Laravel\Pulse\Contracts\Storage::class);
        $this->info('Storage class: ' . get_class($storage));

        // Try direct store to verify storage class works
        $this->info('Testing direct storage...');
        try {
            $storage = app(\Laravel\Pulse\Contracts\Storage::class);
            $value = new \Laravel\Pulse\Value(
                timestamp: now()->timestamp,
                type: 'system',
                key: 'test-direct',
                value: json_encode(['name' => 'test', 'cpu' => 1, 'memory_used' => 100, 'memory_total' => 1000, 'storage' => []]),
            );
            DB::enableQueryLog();
            $storage->store(collect([$value]));
            $testRecord = DB::table('pulse_values')->where('type', 'system')->where('key', 'test-direct')->first();
            $this->info('Direct store: ' . ($testRecord ? 'OK — record written' : 'FAILED — record NOT in DB'));
        } catch (\Throwable $e) {
            $this->error('Direct store FAILED: ' . $e->getMessage());
            $this->error('  at ' . $e->getFile() . ':' . $e->getLine());
        }
        foreach (DB::getQueryLog() as $q) {
            $this->line('SQL: ' . $q['query']);
        }

        // Ingest buffered data into DB
        $this->info('Ingesting buffer...');
        DB::flushQueryLog();
        try {
            Pulse::ingest();
            $this->info('Ingest: OK');
        } catch (\Throwable $e) {
            $this->error('Ingest: FAILED — ' . $e->getMessage());
        }
        $this->info('Ingest SQL count: ' . count(DB::getQueryLog()));

        // Check result
        $system = DB::table('pulse_values')->where('type', 'system')->get(['key', 'value']);
        $this->info("pulse_values (type=system): {$system->count()} record(s)");
        foreach ($system as $row) {
            $this->line("  key={$row->key} value={$row->value}");
        }
    }
}
