<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../tools/uploads/ProductionReadOnlySurvey.php';

class ProductionReadOnlySurveyTest extends TestCase
{
    public function test_survey_hides_names_and_bytes_and_detects_duplicates_conflicts_and_references(): void
    {
        $root = sys_get_temp_dir().'/madarat-readonly-survey-synthetic-'.bin2hex(random_bytes(12));
        mkdir($root, 0700);
        try {
            foreach (['a', 'b'] as $side) {
                mkdir($root.'/'.$side.'/public/cvs', 0700, true);
                mkdir($root.'/'.$side.'/private', 0700, true);
                file_put_contents($root.'/'.$side.'/public/cvs/person-private@example.test.pdf', 'PRIVATE CONTENT');
                file_put_contents($root.'/'.$side.'/public/cvs/conflict.pdf', $side);
                file_put_contents($root.'/'.$side.'/private/.gitignore', 'placeholder');
            }
            file_put_contents($root.'/a/public/cvs/first-only.pdf', 'PRIVATE CONTENT');
            file_put_contents($root.'/b/public/cvs/second-only.pdf', 'SECOND COPY');
            $nonce = 'synthetic-nonce';
            $inventoryA = \ProductionReadOnlySurvey::inventory(['public' => $root.'/a/public', 'local' => $root.'/a/private'], $nonce);
            $inventoryB = \ProductionReadOnlySurvey::inventory(['public' => $root.'/b/public', 'local' => $root.'/b/private'], $nonce);
            $serialized = json_encode($inventoryA);
            $this->assertStringNotContainsString('person-private', $serialized);
            $this->assertStringNotContainsString('PRIVATE CONTENT', $serialized);
            $this->assertStringNotContainsString($root, $serialized);
            $this->assertCount(4, $inventoryA);
            $base = ['format' => 'madarat-read-only-v1', 'complete' => true, 'transaction_read_only' => true,
                'comparison_id' => 'synthetic-comparison', 'database_id' => 'synthetic-database',
                'reference_ids' => [\ProductionReadOnlySurvey::pathId('public', 'cvs/person-private@example.test.pdf', $nonce),
                    \ProductionReadOnlySurvey::pathId('public', 'cvs/missing.pdf', $nonce)],
                'invalid_reference_rows' => 0, 'over_backend_limit_files' => 0];
            $a = ['instance_tag' => 'first', 'files' => $inventoryA, ...$base];
            $b = ['instance_tag' => 'second', 'files' => $inventoryB, ...$base];
            $report = \ProductionReadOnlySurvey::compare($a, $b);
            $this->assertSame(2, $report['counts']['same_path_same_bytes']);
            $this->assertSame(1, $report['counts']['same_path_conflicts']);
            $this->assertSame(1, $report['counts']['only_first']);
            $this->assertSame(1, $report['counts']['only_second']);
            $this->assertSame(1, $report['counts']['referenced_missing_from_both']);
            $this->assertSame(4, $report['counts']['present_without_reference_in_either_snapshot']);
            $this->assertCount(1, $report['same_content_different_paths']);
            $this->assertTrue($report['references_same_between_scans']);
            $b['reference_ids'][] = \ProductionReadOnlySurvey::pathId('public', 'cvs/new-live-upload.pdf', $nonce);
            $changing = \ProductionReadOnlySurvey::compare($a, $b);
            $this->assertFalse($changing['references_same_between_scans']);
            $this->assertSame(2, $changing['counts']['referenced_missing_from_both']);
            $this->assertSame(1, $changing['counts']['stable_references_missing_from_both']);
            $this->assertSame('a', file_get_contents($root.'/a/public/cvs/conflict.pdf'));
        } finally {
            $this->assertStringStartsWith(realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'madarat-readonly-survey-synthetic-', realpath($root));
            (new Filesystem)->deleteDirectory($root);
        }
    }

    public function test_comparison_refuses_incomplete_or_same_instance_reports(): void
    {
        $report = ['format' => 'madarat-read-only-v1', 'complete' => true, 'transaction_read_only' => true,
            'comparison_id' => 'same', 'database_id' => 'same', 'instance_tag' => 'same'];
        $this->expectException(RuntimeException::class);
        \ProductionReadOnlySurvey::compare($report, $report);
    }
}
