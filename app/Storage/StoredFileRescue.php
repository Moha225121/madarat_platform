<?php

namespace App\Storage;

use PDO;
use RuntimeException;
use Throwable;

/** No dependency on new application code being deployed to the web containers. */
final class StoredFileRescue
{
    public function __construct(private PDO $pdo) {}

    public static function preservedKey(string $instance, array $entry): string
    {
        return $instance.'/'.$entry['disk'].'/'.hash('sha256', $entry['path']).'/'.$entry['sha256'];
    }

    public function preserve(string $directory): array
    {
        StoredFileSchema::verifyWithLedger($this->pdo);
        $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        if (! preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $manifest['instance_tag'] ?? '')) {
            throw new RuntimeException('Invalid instance identifier.');
        }
        $run = hash('sha256', json_encode([$manifest['instance_tag'], $manifest['entries'], $manifest['database_references']], JSON_THROW_ON_ERROR));
        $files = new StoredFileRepository($this->pdo);
        $created = 0;
        $reused = 0;
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('SELECT pg_advisory_xact_lock(190733,'.StoredFileSchema::LOCK.')');
            foreach ($manifest['entries'] as $entry) {
                if (! in_array($entry['disk'], ['public', 'local'], true)) {
                    throw new RuntimeException('Unexpected source disk.');
                }
                $base = realpath($directory.'/files/'.$entry['disk']);
                $path = $directory.'/files/'.$entry['disk'].'/'.$entry['path'];
                $resolved = realpath($path);
                if (! $base || ! $resolved || is_link($path)
                    || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR)
                    || filesize($resolved) !== $entry['bytes'] || hash_file('sha256', $resolved) !== $entry['sha256']) {
                    throw new RuntimeException('Snapshot input or hash mismatch.');
                }
                $source = fopen($resolved, 'rb');
                try {
                    $inserted = $files->put('preserved', self::preservedKey($manifest['instance_tag'], $entry), $source, [
                        'original_name' => basename($entry['path']),
                        'mime_type' => (new \finfo(FILEINFO_MIME_TYPE))->file($resolved) ?: 'application/octet-stream',
                    ]);
                } finally {
                    fclose($source);
                }
                $inserted ? $created++ : $reused++;
            }
            $insert = $this->pdo->prepare('INSERT INTO stored_file_import_runs (id,instance_tag,manifest) VALUES (?,?,?) ON CONFLICT (id) DO NOTHING');
            $insert->execute([$run, $manifest['instance_tag'], json_encode($manifest, JSON_THROW_ON_ERROR)]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }

        return ['run' => $run, 'instance_tag' => $manifest['instance_tag'], 'new_copies' => $created, 'verified_existing_copies' => $reused];
    }

    public function reconcile(array $runIds, array $expectedInstances, array $references, bool $apply = false, array $baselineRunIds = [], ?string $approvedBaselineSha = null, bool $writersFrozen = false): array
    {
        if ($apply && $baselineRunIds !== [] && ! $writersFrozen) {
            throw new RuntimeException('A separate actual writer freeze and drain acknowledgement is required.');
        }
        StoredFileSchema::verifyWithLedger($this->pdo);
        if (count($runIds) !== 2 || count(array_unique($expectedInstances)) !== 2) {
            throw new RuntimeException('Select one complete snapshot for each of the two instances.');
        }
        $this->pdo->beginTransaction();
        try {
            if ($apply) {
                $this->assertPublicNamespace();
                $this->pdo->exec("SET LOCAL lock_timeout='5s'");
                $this->pdo->exec('SELECT pg_advisory_xact_lock(190733,'.StoredFileSchema::LOCK.')');
                // Acquire before any target/reference/owner read. These bounded
                // locks belong only in an independently approved final freeze.
                $this->pdo->exec('LOCK TABLE public.job_seeker_profiles,public.company_profiles,public.training_provider_profiles,public.training_courses,public.homepage_advertisements IN SHARE MODE');
            } else {
                $this->pdo->exec('SET TRANSACTION READ ONLY');
            }
            $manifests = [];
            foreach ($runIds as $id) {
                $query = $this->pdo->prepare('SELECT manifest FROM stored_file_import_runs WHERE id=?');
                $query->execute([$id]);
                $json = $query->fetchColumn();
                if (! $json) {
                    throw new RuntimeException('Snapshot run missing.');
                }
                $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                // Use a current agreed baseline, not older captured references.
                $manifest['database_references'] = $references;
                $manifests[] = $manifest;
            }
            $tags = array_column($manifests, 'instance_tag');
            sort($tags);
            sort($expectedInstances);
            if ($tags !== $expectedInstances) {
                throw new RuntimeException('Instance coverage mismatch.');
            }
            $report = \MadaratUploadComparison::compare($manifests);
            $report['database_conflicts'] = [];
            $report['unsupported_references'] = [];
            $report['ownership_conflicts'] = [];
            $files = new StoredFileRepository($this->pdo);
            $available = [];
            foreach ($manifests as $manifest) {
                foreach ($manifest['entries'] as $entry) {
                    $key = self::preservedKey($manifest['instance_tag'], $entry);
                    $metadata = $files->metadata('preserved', $key);
                    if (! $metadata || $metadata['sha256'] !== $entry['sha256'] || (int) $metadata['size'] !== $entry['bytes']) {
                        throw new RuntimeException('Preserved source copy missing or inconsistent.');
                    }
                    // Read and hash actual DB bytes, not just stored metadata.
                    $stream = $files->readStream('preserved', $key);
                    fclose($stream);
                    $available[$entry['disk'].'/'.$entry['path']] = ['key' => $key, ...$metadata];
                }
            }
            $targets = [];
            foreach ($references as $reference) {
                $sourceKey = $reference['disk'].'/'.$reference['path'];
                if (! isset($available[$sourceKey])) {
                    continue;
                }
                $copy = $available[$sourceKey];
                $target = self::target($reference);
                if (! $target || (int) $copy['size'] === 0 || ($target['disk'] === 'public' && ! in_array($copy['mime_type'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true))) {
                    $report['unsupported_references'][] = $reference;

                    continue;
                }
                $existing = $files->metadata($target['disk'], $target['path']);
                if ($existing && ($existing['sha256'] !== $copy['sha256'] || (int) $existing['size'] !== (int) $copy['size'])) {
                    $report['database_conflicts'][] = $reference;
                }
                $targetKey = $target['disk'].'/'.$target['path'];
                $owner = $this->owner($reference);
                if ($existing && $target['disk'] === 'documents' && (int) $existing['owner_user_id'] !== $owner) {
                    $report['ownership_conflicts'][] = $reference;
                }
                if (isset($targets[$targetKey]) && $targets[$targetKey]['owner'] !== $owner && $target['disk'] === 'documents') {
                    $report['ownership_conflicts'][] = $reference;
                }
                $targets[$targetKey] = ['target' => $target, 'copy' => $copy, 'reference' => $reference, 'owner' => $owner];
            }
            $ready = $report['conflicts'] === [] && $report['missing_references'] === []
                && $report['database_conflicts'] === [] && $report['unsupported_references'] === [] && $report['ownership_conflicts'] === [];
            $baselineReview = null;
            if ($baselineRunIds !== []) {
                $baseline = $this->baselineData($baselineRunIds, $expectedInstances, $references);
                $sameMissing = self::canonicalJson(self::tuples($report['missing_references']))
                    === self::canonicalJson($baseline['missing_tuples']);
                $consent = $approvedBaselineSha !== null
                    && preg_match('/\A[a-f0-9]{64}\z/', $approvedBaselineSha)
                    && hash_equals($baseline['sha256'], $approvedBaselineSha);
                $baselineReview = ['sha256' => $baseline['sha256'], 'missing_reference_tuples' => count($baseline['missing_tuples']),
                    'exact_missing_set_matches' => $sameMissing, 'explicit_consent_matches' => (bool) $consent];
                $ready = $sameMissing && $consent && $report['conflicts'] === [] && $report['database_conflicts'] === []
                    && $report['unsupported_references'] === [] && $report['ownership_conflicts'] === [];
            } elseif ($approvedBaselineSha !== null) {
                throw new RuntimeException('Consent requires two explicit original baseline run identifiers.');
            }
            $imported = 0;
            if ($apply && $ready) {
                foreach ($targets as $item) {
                    $stream = $files->readStream('preserved', $item['copy']['key']);
                    try {
                        $files->put($item['target']['disk'], $item['target']['path'], $stream, [
                            'original_name' => $item['copy']['original_name'], 'mime_type' => $item['copy']['mime_type'],
                            'owner_user_id' => $item['owner'],
                        ]);
                    } finally {
                        fclose($stream);
                    }
                    $imported++;
                }
            }
            $this->pdo->commit();

            return ['ready' => $ready, 'applied' => $apply && $ready, 'logical_files' => $imported, 'baseline_review' => $baselineReview,
                'counts' => array_map('count', $report), 'report' => $report];
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    /** Read-only proposal; returns no personal fields, filenames, or missing tuples. */
    public function planMissingBaseline(array $baselineRunIds, array $expectedInstances, array $references): array
    {
        StoredFileSchema::verifyWithLedger($this->pdo);
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Use an independent connection outside a transaction.');
        }
        $this->pdo->exec('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
        try {
            $baseline = $this->baselineData($baselineRunIds, $expectedInstances, $references);
            $result = ['baseline_run_ids' => $baseline['run_ids'], 'sha256' => $baseline['sha256'],
                'missing_reference_tuples' => count($baseline['missing_tuples']), 'writes_performed' => false,
                'current_missing_set_matches_original' => $baseline['current_missing_matches'],
                'consent_recorded' => false, 'cutover_complete' => false];
            $this->pdo->rollBack();

            return $result;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function baselineData(array $ids, array $expectedInstances, array $references): array
    {
        if (count($ids) !== 2 || count(array_unique($ids)) !== 2 || count($expectedInstances) !== 2
            || count(array_unique($expectedInstances)) !== 2) {
            throw new RuntimeException('Exactly two distinct original baseline runs and instances are required.');
        }
        $this->assertPublicNamespace();
        $current = $this->currentReferences();
        if (self::canonicalJson(self::tuples($current)) !== self::canonicalJson(self::tuples($references))) {
            throw new RuntimeException('Reference baseline changed or caller omitted a reference.');
        }
        $owners = [];
        foreach (self::tuples($current) as $reference) {
            $owners[] = [...$reference, 'owner_user_id' => $this->owner($reference)];
        }
        $originals = [];
        $manifestDigests = [];
        $tags = [];
        $files = new StoredFileRepository($this->pdo);
        foreach ($ids as $id) {
            if (! is_string($id) || ! preg_match('/\A[a-f0-9]{64}\z/', $id)) {
                throw new RuntimeException('Original run identifiers must be SHA-256 values.');
            }
            $query = $this->pdo->prepare('SELECT instance_tag,manifest FROM public.stored_file_import_runs WHERE id=?');
            $query->execute([$id]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            if (! $row) {
                throw new RuntimeException('Original baseline run missing.');
            }
            $manifest = json_decode($row['manifest'], true, flags: JSON_THROW_ON_ERROR);
            if ($manifest['instance_tag'] !== $row['instance_tag']) {
                throw new RuntimeException('Original baseline instance mismatch.');
            }
            $seen = [];
            foreach ($manifest['entries'] as $entry) {
                $key = $entry['disk']."\0".$entry['path'];
                if (isset($seen[$key]) || ! in_array($entry['disk'], ['public', 'local'], true)
                    || ! is_int($entry['bytes']) || $entry['bytes'] < 0 || $entry['bytes'] > StoredFileRepository::HARD_MAX_BYTES
                    || ! preg_match('/\A[a-f0-9]{64}\z/', $entry['sha256'])) {
                    throw new RuntimeException('Original baseline inventory is invalid.');
                }
                $seen[$key] = true;
                $savedKey = self::preservedKey($row['instance_tag'], $entry);
                $metadata = $files->metadata('preserved', $savedKey);
                if (! $metadata || $metadata['sha256'] !== $entry['sha256'] || (int) $metadata['size'] !== $entry['bytes']) {
                    throw new RuntimeException('Original preserved copy missing or inconsistent.');
                }
                $stream = $files->readStream('preserved', $savedKey);
                fclose($stream);
            }
            $tags[] = $row['instance_tag'];
            $originals[] = $manifest;
            $manifestDigests[$id] = hash('sha256', self::canonicalJson($manifest));
        }
        sort($tags);
        sort($expectedInstances);
        if ($tags !== $expectedInstances) {
            throw new RuntimeException('Original baseline does not cover both expected instances.');
        }
        $missing = self::tuples(\MadaratUploadComparison::compare($originals)['missing_references']);
        $currentManifests = array_map(static fn (array $manifest): array => [...$manifest, 'database_references' => $current], $originals);
        $currentMissing = self::tuples(\MadaratUploadComparison::compare($currentManifests)['missing_references']);
        foreach ($missing as $reference) {
            if (self::target($reference) === null) {
                throw new RuntimeException('Unsupported missing references cannot be excepted.');
            }
        }
        ksort($manifestDigests);
        $ids = array_keys($manifestDigests);
        $sha = hash('sha256', self::canonicalJson(['format' => 'review-only-missing-baseline-v1',
            'original_runs' => $manifestDigests, 'expected_instances' => $tags, 'missing_tuples' => $missing,
            'current_reference_ownership' => $owners]));

        return ['run_ids' => $ids, 'sha256' => $sha, 'missing_tuples' => $missing,
            'current_missing_matches' => self::canonicalJson($missing) === self::canonicalJson($currentMissing)];
    }

    private function currentReferences(): array
    {
        $result = [];
        foreach (['job_seeker_profiles' => ['cv_path'], 'company_profiles' => ['logo_path'],
            'training_provider_profiles' => ['logo_path', 'profile_image_path'], 'training_courses' => ['cover_image_path'],
            'homepage_advertisements' => ['image_path']] as $table => $columns) {
            foreach ($columns as $column) {
                foreach ($this->pdo->query('SELECT id,'.$column.' FROM public.'.$table.' WHERE '.$column.' IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $result[] = ['table' => $table, 'id' => $row['id'], 'column' => $column, 'disk' => 'public', 'path' => $row[$column]];
                }
            }
        }

        return $result;
    }

    private function assertPublicNamespace(): void
    {
        $namespace = $this->pdo->query("SELECT current_schema()='public' AND to_regclass('stored_files')=to_regclass('public.stored_files') AND to_regclass('stored_file_import_runs')=to_regclass('public.stored_file_import_runs') AND to_regclass('migrations')=to_regclass('public.migrations')")->fetchColumn();
        if ($namespace !== true) {
            throw new RuntimeException('Public table resolution is required.');
        }
    }

    private static function tuples(array $references): array
    {
        $set = [];
        foreach ($references as $reference) {
            $tuple = ['table' => $reference['table'], 'id' => (int) $reference['id'], 'column' => $reference['column'],
                'disk' => $reference['disk'], 'path' => $reference['path']];
            $set[self::canonicalJson($tuple)] = $tuple;
        }
        ksort($set);

        return array_values($set);
    }

    private static function canonicalJson(mixed $value): string
    {
        $canonical = static function (mixed $item) use (&$canonical): mixed {
            if (! is_array($item)) {
                return $item;
            }
            if (! array_is_list($item)) {
                ksort($item);
            }

            return array_map($canonical, $item);
        };

        return json_encode($canonical($value), JSON_THROW_ON_ERROR);
    }

    private static function target(array $reference): ?array
    {
        $path = $reference['path'];
        if ($reference['disk'] !== 'public' || ! is_string($path)
            || ! preg_match('~\A(cvs|company-logos|training-providers|training-courses|homepage-advertisements)/[a-zA-Z0-9][a-zA-Z0-9._-]*\z~', $path)) {
            return null;
        }

        return ['disk' => str_starts_with($path, 'cvs/') ? 'documents' : 'public', 'path' => $path];
    }

    private function owner(array $reference): ?int
    {
        $columns = ['job_seeker_profiles' => ['cv_path'], 'company_profiles' => ['logo_path'],
            'training_provider_profiles' => ['logo_path', 'profile_image_path'], 'training_courses' => ['cover_image_path'],
            'homepage_advertisements' => ['image_path']];
        if (! in_array($reference['column'], $columns[$reference['table']] ?? [], true)) {
            throw new RuntimeException('Unexpected database reference.');
        }
        if ($reference['table'] === 'homepage_advertisements') {
            return null;
        }
        $sql = $reference['table'] === 'training_courses'
            ? 'SELECT p.user_id FROM public.training_courses c JOIN public.training_provider_profiles p ON p.id=c.training_provider_id WHERE c.id=? AND c.cover_image_path=?'
            : 'SELECT user_id FROM public.'.$reference['table'].' WHERE id=? AND '.$reference['column'].'=?';
        $query = $this->pdo->prepare($sql);
        $query->execute([$reference['id'], $reference['path']]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Reference changed during import; retry under an approved write freeze.');
        }

        return (int) $id;
    }
}
