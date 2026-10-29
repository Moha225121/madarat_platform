<?php

use App\Storage\StoredFileRescue;
use App\Storage\StoredFileSchema;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;

// Reviewed bundle layout can be placed under /tmp WITHOUT replacing web code:
// tools/uploads/{database-rescue,snapshot,compare}.php and app/Storage/{...}.php.
require_once __DIR__.'/snapshot.php';
require_once __DIR__.'/compare.php';
require_once __DIR__.'/../../app/Storage/StoredFileSchema.php';
require_once __DIR__.'/../../app/Storage/StoredFileRepository.php';
require_once __DIR__.'/../../app/Storage/StoredFileRescue.php';

ini_set('display_errors', '0');
ini_set('log_errors', '0');
umask(0077);
try {
    $options = getopt('', ['source:', 'action:', 'apply', 'approved-production-write', 'writers-frozen', 'runs:', 'instances:', 'report:', 'baseline-runs:', 'approved-baseline-sha:']);
    $source = realpath($options['source'] ?? getcwd());
    $action = $options['action'] ?? 'scan';
    $apply = isset($options['apply']);
    if ($apply && ! isset($options['approved-production-write'])) {
        throw new RuntimeException('Write acknowledgement missing.');
    }
    if (! in_array($action, ['scan', 'prepare', 'import', 'finalize', 'plan-baseline'], true)) {
        throw new RuntimeException('Unknown action.');
    }
    if ($action === 'plan-baseline' && $apply) {
        throw new RuntimeException('Baseline proposals are read-only and never record consent.');
    }
    // Existing Laravel runtime only: no new provider, migration or web deployment.
    require_once $source.'/vendor/autoload.php';
    $app = require $source.'/bootstrap/app.php';
    (new LoadEnvironmentVariables)->bootstrap($app);
    (new LoadConfiguration)->bootstrap($app);
    (new DatabaseServiceProvider($app))->register();
    $pdo = $app['db']->connection()->getPdo();
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
        throw new RuntimeException('PostgreSQL required.');
    }
    if ($action === 'prepare') {
        if ($apply) {
            $result = ['created' => StoredFileSchema::prepareWithLedger($pdo)];
        } else {
            $tables = (int) $pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_name IN ('stored_files','stored_file_import_runs')")->fetchColumn();
            $query = $pdo->prepare('SELECT count(*) FROM migrations WHERE migration=?');
            $query->execute([StoredFileSchema::MIGRATION]);
            $records = (int) $query->fetchColumn();
            if ($tables === 2 && $records === 1) {
                StoredFileSchema::verify($pdo);
            } elseif ($tables !== 0 || $records !== 0) {
                throw new RuntimeException('Schema/ledger conflict.');
            }
            $result = ['dry_run' => true, 'tables' => $tables, 'migration_records' => $records, 'would_create' => $tables === 0];
        }
    } elseif ($action === 'plan-baseline') {
        if (isset($options['approved-baseline-sha'])) {
            throw new RuntimeException('A read-only proposal cannot accept its own consent.');
        }
        $references = MadaratUploadSnapshot::references($source);
        $result = (new StoredFileRescue($pdo))->planMissingBaseline(
            explode(',', $options['baseline-runs'] ?? ''), explode(',', $options['instances'] ?? ''), $references,
        );
    } elseif ($action === 'finalize') {
        if ($apply && ! isset($options['writers-frozen'])) {
            throw new RuntimeException('Separate write-freeze approval and drain are required.');
        }
        $references = MadaratUploadSnapshot::references($source);
        $baselineRuns = isset($options['baseline-runs']) ? explode(',', $options['baseline-runs']) : [];
        $result = (new StoredFileRescue($pdo))->reconcile(
            explode(',', $options['runs'] ?? ''), explode(',', $options['instances'] ?? ''), $references, $apply,
            $baselineRuns, $options['approved-baseline-sha'] ?? null, isset($options['writers-frozen']),
        );
        if (isset($options['report'])) {
            MadaratUploadSnapshot::writeExclusive($options['report'], json_encode($result['report'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }
        unset($result['report']);
    } else {
        $references = MadaratUploadSnapshot::references($source);
        $entries = [];
        foreach (['public' => '/storage/app/public', 'local' => '/storage/app/private'] as $disk => $suffix) {
            foreach (MadaratUploadSnapshot::inventory($source.$suffix) as $entry) {
                $entries[] = ['disk' => $disk, ...$entry];
            }
        }
        $result = ['dry_run' => true, 'instance_tag' => substr(hash('sha256', gethostname()), 0, 12),
            'files' => count($entries), 'bytes' => array_sum(array_column($entries, 'bytes')), 'references' => count($references)];
        if ($action === 'import' && $apply) {
            StoredFileSchema::verify($pdo);
            $snapshot = MadaratUploadSnapshot::create($source, sys_get_temp_dir(), $references);
            $result = (new StoredFileRescue($pdo))->preserve($snapshot['directory']);
        }
        if (isset($options['report'])) {
            MadaratUploadSnapshot::writeExclusive($options['report'], json_encode(['entries' => $entries, 'references' => $references, ...$result], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), PHP_EOL;
    if (isset($result['ready']) && ! $result['ready']) {
        exit(2);
    }
} catch (Throwable) {
    fwrite(STDERR, "Rescue stopped. No secret/error details printed. Keep both source containers; review schema, sources and private reports.\n");
    exit(1);
}
