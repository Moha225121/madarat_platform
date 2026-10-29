<?php

namespace Tests\StoragePostgres;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Runs against a new synthetic loopback database, never the application database. */
class MissingBaselineRescueTest extends TestCase
{
    public function test_exact_baseline_and_real_competing_writer_checks_on_a_new_synthetic_database(): void
    {
        $root = dirname(__DIR__, 2);
        $probe = $root.'/tests/Support/missing_baseline_pgsql_probe.php';
        $this->assertFileExists($probe);
        $process = new Process([PHP_BINARY, '-d', 'extension=pdo_pgsql', $probe, $root], $root, ['PAO_DISABLE' => '1']);
        $process->setTimeout(120);
        $process->mustRun();
        $this->assertSame('', $process->getErrorOutput());
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('local_review_prototype_passed', $result['status']);
        $this->assertSame('18.6', $result['postgresql']);
        $this->assertStringStartsWith('madarat_baseline_review_', $result['database']);
        $this->assertSame(15, $result['tests']);
        $this->assertSame(43, $result['assertions']);
        $this->assertContains('real_competing_owner_writer_blocked_then_released', $result['cases']);
        $this->assertFalse($result['production_accessed']);
        $this->assertFalse($result['production_consent_recorded']);
        $this->assertFalse($result['cutover_complete']);
    }
}
