<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../tools/uploads/snapshot.php';
require_once __DIR__.'/../../tools/uploads/compare.php';

class UploadSnapshotTest extends TestCase
{
    public function test_reference_versions_are_retained_when_a_record_changes_between_snapshots(): void
    {
        $reference = ['table' => 'job_seeker_profiles', 'id' => 1, 'column' => 'cv_path', 'disk' => 'public'];
        $report = \MadaratUploadComparison::compare([
            ['entries' => [], 'database_references' => [['path' => 'cvs/before.pdf', ...$reference]]],
            ['entries' => [], 'database_references' => [['path' => 'cvs/after.pdf', ...$reference]]],
        ]);
        $this->assertSame(['cvs/before.pdf', 'cvs/after.pdf'], array_column($report['missing_references'], 'path'));
    }

    public function test_separate_snapshots_preserve_bytes_and_classify_conflicts_without_merging(): void
    {
        $parent = sys_get_temp_dir().'/madarat-synthetic-snapshots-'.bin2hex(random_bytes(12));
        mkdir($parent, 0700);
        try {
            foreach (['a', 'b'] as $name) {
                mkdir($parent.'/'.$name.'/storage/app/public/cvs', 0700, true);
                mkdir($parent.'/'.$name.'/storage/app/private', 0700, true);
                file_put_contents($parent.'/'.$name.'/storage/app/public/cvs/same.pdf', 'identical');
                file_put_contents($parent.'/'.$name.'/storage/app/public/cvs/conflict.pdf', $name);
                file_put_contents($parent.'/'.$name.'/storage/app/private/.gitignore', 'not necessarily user data');
            }
            $references = [['disk' => 'public', 'path' => 'cvs/same.pdf', 'table' => 'job_seeker_profiles', 'column' => 'cv_path', 'id' => 1],
                ['disk' => 'public', 'path' => 'cvs/missing.pdf', 'table' => 'job_seeker_profiles', 'column' => 'cv_path', 'id' => 2]];
            $a = \MadaratUploadSnapshot::create($parent.'/a', $parent, $references);
            $b = \MadaratUploadSnapshot::create($parent.'/b', $parent, $references);
            $this->assertNotSame($a['directory'], $b['directory']);
            $this->assertSame(3, $a['files']);
            $this->assertSame(hash_file('sha256', $a['directory'].'/snapshot.tar'), $a['archive_sha256']);
            $archive = new \PharData($a['directory'].'/snapshot.tar');
            $this->assertSame('a', $archive['files/public/cvs/conflict.pdf']->getContent());
            unset($archive);
            $report = \MadaratUploadComparison::compare([
                json_decode(file_get_contents($a['directory'].'/manifest.json'), true),
                json_decode(file_get_contents($b['directory'].'/manifest.json'), true),
            ]);
            $this->assertCount(2, $report['identical_paths']);
            $this->assertCount(1, $report['conflicts']);
            $this->assertArrayHasKey('public/cvs/conflict.pdf', $report['conflicts']);
            $this->assertCount(1, $report['missing_references']);
            $this->assertContains('local/.gitignore', $report['unreferenced']);
            $this->assertSame('a', file_get_contents($parent.'/a/storage/app/public/cvs/conflict.pdf'));
            $this->assertSame('b', file_get_contents($parent.'/b/storage/app/public/cvs/conflict.pdf'));
            $manifestBefore = file_get_contents($a['directory'].'/manifest.json');
            $refused = false;
            try {
                \MadaratUploadSnapshot::writeExclusive($a['directory'].'/manifest.json', 'overwrite');
            } catch (\RuntimeException) {
                $refused = true;
            }
            $this->assertTrue($refused, 'Must not overwrite a manifest.');
            $this->assertSame($manifestBefore, file_get_contents($a['directory'].'/manifest.json'));
            $this->assertJson(file_get_contents($a['directory'].'/manifest.json'));
        } finally {
            // Only the known synthetic root created by this test may be removed.
            $this->assertStringStartsWith(realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'madarat-synthetic-snapshots-', realpath($parent));
            (new Filesystem)->deleteDirectory($parent);
        }
    }
}
