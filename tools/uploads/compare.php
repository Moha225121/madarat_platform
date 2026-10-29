<?php

require_once __DIR__.'/snapshot.php';

final class MadaratUploadComparison
{
    public static function compare(array $manifests): array
    {
        $paths = [];
        $references = [];
        foreach ($manifests as $index => $manifest) {
            foreach ($manifest['entries'] as $entry) {
                $paths[$entry['disk'].'/'.$entry['path']][] = ['snapshot' => $index, ...$entry];
            }
            foreach ($manifest['database_references'] ?? [] as $reference) {
                $references[$reference['table'].':'.$reference['id'].':'.$reference['column'].':'.$reference['disk'].':'.$reference['path']] = $reference;
            }
        }
        $report = ['unique_paths' => [], 'identical_paths' => [], 'conflicts' => [], 'unreferenced' => [], 'missing_references' => []];
        $referenced = [];
        foreach ($references as $reference) {
            $path = $reference['disk'].'/'.$reference['path'];
            $referenced[$path] = true;
            if (! isset($paths[$path])) {
                $report['missing_references'][] = $reference;
            }
        }
        foreach ($paths as $path => $copies) {
            $hashes = array_unique(array_map(fn ($copy) => $copy['sha256'].':'.$copy['bytes'], $copies));
            $kind = count($hashes) > 1 ? 'conflicts' : (count($copies) > 1 ? 'identical_paths' : 'unique_paths');
            $report[$kind][$path] = $copies;
            if (! isset($referenced[$path])) {
                $report['unreferenced'][] = $path;
            }
        }

        return $report;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');
    umask(0077);
    try {
        if ($argc < 4) {
            throw new RuntimeException('Two manifests and a private new report path are required.');
        }
        $reportPath = $argv[$argc - 1];
        $manifests = array_map(fn ($path) => json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR), array_slice($argv, 1, -1));
        $report = MadaratUploadComparison::compare($manifests);
        MadaratUploadSnapshot::writeExclusive($reportPath, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode(array_map('count', $report), JSON_THROW_ON_ERROR), PHP_EOL;
    } catch (Throwable) {
        fwrite(STDERR, "Comparison incomplete; no report details printed.\n");
        exit(1);
    }
}
