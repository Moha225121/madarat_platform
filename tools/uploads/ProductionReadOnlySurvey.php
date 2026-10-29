<?php

/** Standalone, in-memory survey. No file creation, migration or import code. */
final class ProductionReadOnlySurvey
{
    public static function pathId(string $disk, string $path, string $nonce): string
    {
        return hash_hmac('sha256', $disk."\0".$path, $nonce);
    }

    public static function inventory(array $roots, string $nonce): array
    {
        $entries = [];
        foreach ($roots as $disk => $root) {
            $base = realpath($root);
            if (! $base || is_link($root) || ! is_dir($base)) {
                throw new RuntimeException('Inventory incomplete.');
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isLink() || ! $file->isFile()) {
                    throw new RuntimeException('Inventory incomplete.');
                }
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($base) + 1));
                $stream = fopen($file->getPathname(), 'rb');
                if (! $stream) {
                    throw new RuntimeException('Inventory incomplete.');
                }
                try {
                    $before = fstat($stream);
                    $hash = hash_init('sha256');
                    $bytes = hash_update_stream($hash, $stream);
                    $after = fstat($stream);
                    if ($bytes !== $before['size'] || $before['size'] !== $after['size']
                        || $before['mtime'] !== $after['mtime'] || $before['ino'] !== $after['ino']) {
                        throw new RuntimeException('Source changed during inventory.');
                    }
                    $entries[] = ['path_id' => self::pathId($disk, $relative, $nonce), 'disk' => $disk,
                        'bytes' => $bytes, 'sha256' => hash_final($hash)];
                } finally {
                    fclose($stream);
                }
            }
        }
        usort($entries, fn ($a, $b) => strcmp($a['path_id'], $b['path_id']));

        return $entries;
    }

    public static function collect(PDO $pdo, array $roots, string $nonce, string $connectionIdentity): array
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql' || $pdo->inTransaction()) {
            throw new RuntimeException('A new PostgreSQL connection is required.');
        }
        $started = gmdate(DATE_ATOM);
        $pdo->exec('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
        try {
            if ($pdo->query('SHOW transaction_read_only')->fetchColumn() !== 'on') {
                throw new RuntimeException('Read-only transaction could not be established.');
            }
            $database = $pdo->query('SELECT current_database()')->fetchColumn();
            $databaseBytes = (int) $pdo->query('SELECT pg_database_size(current_database())')->fetchColumn();
            $exists = $pdo->query("SELECT to_regclass('public.stored_files')")->fetchColumn() !== null;
            $rowsBefore = $exists ? (int) $pdo->query('SELECT count(*) FROM public.stored_files')->fetchColumn() : null;
            $migration = $pdo->prepare('SELECT count(*) FROM public.migrations WHERE migration=?');
            $migration->execute(['2026_10_28_000001_create_stored_files_table']);
            $migrationRecords = (int) $migration->fetchColumn();
            $references = [];
            $referenceRows = 0;
            $invalidReferences = 0;
            foreach (['job_seeker_profiles' => ['cv_path'], 'company_profiles' => ['logo_path'],
                'training_provider_profiles' => ['logo_path', 'profile_image_path'],
                'training_courses' => ['cover_image_path'], 'homepage_advertisements' => ['image_path']] as $table => $columns) {
                foreach ($columns as $column) {
                    // Only file references, never names, IDs, contacts or contents.
                    $query = $pdo->query('SELECT '.$column.' FROM public.'.$table.' WHERE '.$column.' IS NOT NULL');
                    while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
                        $referenceRows++;
                        if (! is_string($row[$column]) || $row[$column] === '') {
                            $invalidReferences++;

                            continue;
                        }
                        $references[] = self::pathId('public', $row[$column], $nonce);
                    }
                }
            }
            $references = array_values(array_unique($references));
            sort($references);
            $first = self::inventory($roots, $nonce);
            $second = self::inventory($roots, $nonce);
            $perDisk = [];
            foreach (array_keys($roots) as $disk) {
                $files = array_values(array_filter($first, fn ($entry) => $entry['disk'] === $disk));
                $perDisk[$disk] = ['files' => count($files), 'bytes' => array_sum(array_column($files, 'bytes'))];
            }
            $rowsAfter = $exists ? (int) $pdo->query('SELECT count(*) FROM public.stored_files')->fetchColumn() : null;

            return ['format' => 'madarat-read-only-v1', 'complete' => $first === $second,
                'comparison_id' => hash('sha256', $nonce),
                'database_id' => hash_hmac('sha256', $connectionIdentity."\0".$database, $nonce),
                'instance_tag' => substr(hash('sha256', gethostname()), 0, 12),
                'started_at' => $started, 'finished_at' => gmdate(DATE_ATOM),
                'transaction_read_only' => true, 'stored_files_exists' => $exists,
                'stored_files_rows_before' => $rowsBefore, 'stored_files_rows_after' => $rowsAfter,
                'file_migration_records' => $migrationRecords, 'database_bytes' => $databaseBytes,
                'cluster_free_bytes' => null, 'per_disk' => $perDisk,
                'file_count' => count($first), 'total_bytes' => array_sum(array_column($first, 'bytes')),
                'files' => $first, 'reference_ids' => $references, 'reference_rows' => $referenceRows,
                'invalid_reference_rows' => $invalidReferences,
                'over_backend_limit_files' => count(array_filter($first, fn ($entry) => $entry['bytes'] > 67108864))];
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    public static function compare(array $first, array $second): array
    {
        foreach ([$first, $second] as $report) {
            if (($report['format'] ?? null) !== 'madarat-read-only-v1' || ($report['complete'] ?? false) !== true
                || ($report['transaction_read_only'] ?? false) !== true) {
                throw new RuntimeException('An incomplete or unsupported survey cannot be compared.');
            }
        }
        if ($first['comparison_id'] !== $second['comparison_id'] || $first['database_id'] !== $second['database_id']
            || $first['instance_tag'] === $second['instance_tag']) {
            throw new RuntimeException('Comparison identity/instance mismatch.');
        }
        $a = array_column($first['files'], null, 'path_id');
        $b = array_column($second['files'], null, 'path_id');
        if (count($a) !== count($first['files']) || count($b) !== count($second['files'])) {
            throw new RuntimeException('Duplicate inventory identifier.');
        }
        $identical = [];
        $conflicts = [];
        foreach (array_intersect(array_keys($a), array_keys($b)) as $id) {
            if ($a[$id]['sha256'] === $b[$id]['sha256'] && $a[$id]['bytes'] === $b[$id]['bytes']) {
                $identical[] = $id;
            } else {
                $conflicts[] = $id;
            }
        }
        $allPaths = array_values(array_unique([...array_keys($a), ...array_keys($b)]));
        $allReferences = array_values(array_unique([...$first['reference_ids'], ...$second['reference_ids']]));
        $stableReferences = array_intersect($first['reference_ids'], $second['reference_ids']);
        $contentGroups = [];
        foreach ([...$first['files'], ...$second['files']] as $entry) {
            $contentGroups[$entry['sha256'].':'.$entry['bytes']][$entry['path_id']] = true;
        }
        $sameContentDifferentPaths = array_values(array_map('array_keys', array_filter($contentGroups, fn ($ids) => count($ids) > 1)));
        $ids = ['same_path_same_bytes' => $identical, 'same_path_conflicts' => $conflicts,
            'only_first' => array_values(array_diff(array_keys($a), array_keys($b))),
            'only_second' => array_values(array_diff(array_keys($b), array_keys($a))),
            'referenced_missing_from_both' => array_values(array_diff($allReferences, $allPaths)),
            'stable_references_missing_from_both' => array_values(array_diff($stableReferences, $allPaths)),
            'present_without_reference_in_either_snapshot' => array_values(array_diff($allPaths, $allReferences))];
        $leftRefs = $first['reference_ids'];
        $rightRefs = $second['reference_ids'];
        sort($leftRefs);
        sort($rightRefs);

        $instances = [];
        foreach ([$first, $second] as $report) {
            $instances[] = ['instance_tag' => $report['instance_tag'], 'file_count' => count($report['files']),
                'bytes' => array_sum(array_column($report['files'], 'bytes')), 'per_disk' => $report['per_disk'] ?? null,
                'started_at' => $report['started_at'] ?? null, 'finished_at' => $report['finished_at'] ?? null];
        }

        return ['instances' => $instances, 'counts' => array_map('count', $ids), 'opaque_path_ids' => $ids,
            'observed_distinct_paths' => count($allPaths),
            'unreferenced_per_instance' => [count(array_diff(array_keys($a), $allReferences)), count(array_diff(array_keys($b), $allReferences))],
            'same_content_different_paths' => $sameContentDifferentPaths,
            'references_same_between_scans' => $leftRefs === $rightRefs,
            'invalid_reference_rows' => [$first['invalid_reference_rows'], $second['invalid_reference_rows']],
            'over_backend_limit_files' => [$first['over_backend_limit_files'], $second['over_backend_limit_files']],
            'live_inventory_not_a_write_freeze' => true];
    }
}
