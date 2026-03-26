<?php

namespace Tests\Unit;

use App\Recorders\RemoteServers;
use Laravel\Pulse\Pulse;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemoteServersTest extends TestCase
{
    private RemoteServers $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = new RemoteServers(
            $this->createStub(Pulse::class),
            $this->createStub(Repository::class),
        );
    }

    #[Test]
    public function it_parses_stats_for_a_single_directory(): void
    {
        $raw = implode("\n", [
            '8000000',  // mem total KB  (~7813 MB)
            '4000000',  // mem available KB (~3906 MB used)
            '25',       // CPU %
            '500000',   // disk used KB   (~488 MB)
            '2000000',  // disk total KB  (~1953 MB)
        ]);

        $result = $this->recorder->parseStats($raw, ['/']);

        $this->assertNotNull($result);
        $this->assertSame(25, $result['cpu']);
        $this->assertSame(7813, $result['memory_total']);
        $this->assertSame(3906, $result['memory_used']);
        $this->assertCount(1, $result['storage']);
        $this->assertSame('/', $result['storage'][0]['directory']);
        $this->assertSame(488, $result['storage'][0]['used']);
        $this->assertSame(1953, $result['storage'][0]['total']);
    }

    #[Test]
    public function it_parses_stats_for_multiple_directories(): void
    {
        $raw = implode("\n", [
            '16000000', // mem total KB
            '8000000',  // mem available KB
            '50',       // CPU %
            '100000',   // / used KB
            '500000',   // / total KB
            '200000',   // /data used KB
            '1000000',  // /data total KB
        ]);

        $result = $this->recorder->parseStats($raw, ['/', '/data']);

        $this->assertNotNull($result);
        $this->assertSame(50, $result['cpu']);
        $this->assertCount(2, $result['storage']);
        $this->assertSame('/', $result['storage'][0]['directory']);
        $this->assertSame('/data', $result['storage'][1]['directory']);
    }

    #[Test]
    public function it_returns_null_when_output_is_empty(): void
    {
        $result = $this->recorder->parseStats('', ['/']);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_when_lines_are_insufficient(): void
    {
        $raw = implode("\n", [
            '8000000',
            '4000000',
            // CPU ve disk satırları eksik
        ]);

        $result = $this->recorder->parseStats($raw, ['/']);

        $this->assertNull($result);
    }

    #[Test]
    public function it_converts_kb_to_mb_correctly(): void
    {
        $raw = implode("\n", [
            '1024',  // 1 MB total
            '512',   // 0.5 MB available → 0.5 MB used → rounds to 0
            '10',
            '2048',  // 2 MB used
            '4096',  // 4 MB total
        ]);

        $result = $this->recorder->parseStats($raw, ['/']);

        $this->assertSame(1, $result['memory_total']);
        $this->assertSame(2, $result['storage'][0]['used']);
        $this->assertSame(4, $result['storage'][0]['total']);
    }
}
