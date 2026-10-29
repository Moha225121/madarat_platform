<?php

// LOCAL SYNTHETIC POSTGRESQL ONLY. No production bindings or application boot.
$_SERVER['PAO_DISABLE'] = '1';
putenv('PAO_DISABLE=1');
$source = $argv[1] ?? throw new RuntimeException('Pass the original local checkout.');
require $source.'/vendor/autoload.php';
require $source.'/app/Storage/StoredFileSchema.php';
require $source.'/app/Storage/StoredFileRepository.php';
require $source.'/tools/uploads/compare.php';
require $source.'/app/Storage/StoredFileRescue.php';
use App\Storage\StoredFileRepository;
use App\Storage\StoredFileRescue as PrototypeStoredFileRescue;
use App\Storage\StoredFileSchema;

// Test-only hook after the real target-owner SELECT has returned its value.
class ReviewOwnerProbeStatement extends PDOStatement
{
    public static ?Closure $afterOwnerRead = null;

    protected function __construct() {}

    public function fetchColumn(int $column = 0): mixed
    {
        $value = parent::fetchColumn($column);
        if (self::$afterOwnerRead !== null && str_starts_with($this->queryString, 'SELECT user_id FROM public.job_seeker_profiles')) {
            $callback = self::$afterOwnerRead;
            self::$afterOwnerRead = null;
            $callback();
        }

        return $value;
    }
}

$admin = new PDO('pgsql:host=127.0.0.1;port=55432;dbname=postgres;sslmode=disable', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($admin->query('SELECT host(inet_server_addr())')->fetchColumn() !== '127.0.0.1') {
    throw new RuntimeException('Loopback PostgreSQL is mandatory.');
}
$database = 'madarat_baseline_review_'.bin2hex(random_bytes(5));
$admin->exec('CREATE DATABASE "'.$database.'"');
$p = new PDO('pgsql:host=127.0.0.1;port=55432;dbname='.$database.';sslmode=disable', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($p->query('SELECT current_database()')->fetchColumn() !== $database || ! str_starts_with($database, 'madarat_baseline_review_')) {
    throw new RuntimeException('Disposable database identity mismatch.');
}
$p->exec("SET search_path TO public;
CREATE TABLE users(id bigint PRIMARY KEY);
CREATE TABLE migrations(id serial PRIMARY KEY,migration varchar(255) NOT NULL,batch int NOT NULL);
CREATE TABLE job_seeker_profiles(id bigint PRIMARY KEY,user_id bigint NOT NULL,cv_path text);
CREATE TABLE company_profiles(id bigint PRIMARY KEY,user_id bigint NOT NULL,logo_path text);
CREATE TABLE training_provider_profiles(id bigint PRIMARY KEY,user_id bigint NOT NULL,logo_path text,profile_image_path text);
CREATE TABLE training_courses(id bigint PRIMARY KEY,training_provider_id bigint,cover_image_path text);
CREATE TABLE homepage_advertisements(id bigint PRIMARY KEY,image_path text);
INSERT INTO users VALUES(1),(2),(3),(4),(5);
INSERT INTO job_seeker_profiles VALUES(1,1,'cvs/present.pdf'),(2,3,'cvs/already-missing.pdf');");
StoredFileSchema::prepareWithLedger($p);
$files = new StoredFileRepository($p);
$rescue = new PrototypeStoredFileRescue($p);
$assertions = 0;
$tests = [];
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$expectException = static function (callable $call, string $message) use ($assert): void {
    try {
        $call();
    } catch (Throwable) {
        $assert(true, $message);

        return;
    }
    $assert(false, $message);
};
$refs = static function () use ($p): array {
    $out = [];
    foreach (['job_seeker_profiles' => ['cv_path'], 'company_profiles' => ['logo_path'], 'training_provider_profiles' => ['logo_path', 'profile_image_path'], 'training_courses' => ['cover_image_path'], 'homepage_advertisements' => ['image_path']] as $table => $columns) {
        foreach ($columns as $column) {
            foreach ($p->query('SELECT id,'.$column.' FROM '.$table.' WHERE '.$column.' IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[] = ['table' => $table, 'id' => $row['id'], 'column' => $column, 'disk' => 'public', 'path' => $row[$column]];
            }
        }
    }

    return $out;
};
$put = static function (string $disk, string $path, string $bytes, array $meta = []) use ($files): void {
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, $bytes);
    rewind($stream);
    try {
        $files->put($disk, $path, $stream, $meta);
    } finally {
        fclose($stream);
    }
};
$makeRun = static function (string $tag, array $entries, array $references) use ($put, $p): string {
    $manifest = ['format' => 1, 'instance_tag' => $tag, 'created_at' => '2026-10-29T00:00:00+00:00', 'entries' => [], 'database_references' => $references];
    foreach ($entries as [$disk,$path,$bytes,$mime]) {
        $entry = ['disk' => $disk, 'path' => $path, 'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
        $manifest['entries'][] = $entry;
        $put('preserved', PrototypeStoredFileRescue::preservedKey($tag, $entry), $bytes, ['original_name' => basename($path), 'mime_type' => $mime]);
    }
    // Independently generated SHA values, never copied from production/checker constants.
    $id = hash('sha256', 'synthetic-'.random_bytes(32));
    $q = $p->prepare('INSERT INTO stored_file_import_runs(id,instance_tag,manifest) VALUES(?,?,?)');
    $q->execute([$id, $tag, json_encode($manifest, JSON_THROW_ON_ERROR)]);

    return $id;
};
$tags = ['instance_alpha', 'instance_beta'];
$baseEntries = [['public', 'cvs/present.pdf', '%PDF-1.4 synthetic existing CV', 'application/pdf'], ['local', '.gitignore', 'placeholder', 'text/plain']];
$ids = [$makeRun($tags[0], $baseEntries, $refs()), $makeRun($tags[1], $baseEntries, $refs())];
$business = static fn (): array => $p->query('SELECT * FROM job_seeker_profiles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$savedCount = static fn (): int => (int) $p->query("SELECT count(*) FROM stored_files WHERE disk='preserved'")->fetchColumn();
$canonicalCount = static fn (): int => (int) $p->query("SELECT count(*) FROM stored_files WHERE disk<>'preserved'")->fetchColumn();
$initialBusiness = $business();
$initialSaved = $savedCount();
$initialPreserved = $p->query("SELECT disk,path,size,sha256 FROM stored_files WHERE disk='preserved' ORDER BY path")->fetchAll(PDO::FETCH_ASSOC);
$initialManifests = $p->query('SELECT id,manifest FROM stored_file_import_runs ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);

$r = $rescue->reconcile($ids, $tags, $refs(), true);
$assert(! $r['ready'] && ! $r['applied'] && $canonicalCount() === 0, 'Default reconciliation must remain strict even for apply.');
$tests[] = 'default_blocks_known_missing';
$beforePlanRows = (int) $p->query('SELECT count(*) FROM stored_files')->fetchColumn();
$plan = $rescue->planMissingBaseline($ids, $tags, $refs());
$assert($plan['missing_reference_tuples'] === 1 && strlen($plan['sha256']) === 64, 'Plan must identify the exact synthetic missing tuple.');
$assert($plan['current_missing_set_matches_original'], 'Plan must report exact current/original missing-set equality.');
$assert((int) $p->query('SELECT count(*) FROM stored_files')->fetchColumn() === $beforePlanRows, 'Planning must not write stored files.');
$assert(! str_contains(json_encode($plan), 'already-missing') && ! str_contains(json_encode($plan), 'present.pdf'), 'Plan output must omit private paths.');
$tests[] = 'read_only_private_proposal';
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, str_repeat('0', 64), true);
$assert(! $r['ready'] && ! $r['applied'] && $canonicalCount() === 0, 'Wrong consent must not import.');
$expectException(fn () => $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256']), 'Freeze acknowledgement must be independently required.');
$tests[] = 'wrong_consent_and_missing_freeze_block';
$r = $rescue->reconcile($ids, $tags, $refs(), false, $ids);
$assert(! $r['ready'] && ! $r['baseline_review']['explicit_consent_matches'], 'Planning is not consent.');
$expectException(fn () => $rescue->planMissingBaseline([substr($ids[0], 0, 62), $ids[1]], $tags, $refs()), 'Short identifier must fail.');
$expectException(fn () => $rescue->planMissingBaseline([$ids[0], $ids[0]], $tags, $refs()), 'Duplicate original run must fail.');
$expectException(fn () => $rescue->planMissingBaseline($ids, [$tags[0], 'wrong_instance'], $refs()), 'Original instance coverage must fail.');
$expectException(fn () => $rescue->planMissingBaseline($ids, $tags, array_slice($refs(), 0, 1)), 'Caller cannot hide references.');
$tests[] = 'baseline_identity_and_complete_reference_guards';

$p->exec("INSERT INTO job_seeker_profiles VALUES(3,5,'cvs/new-missing.pdf')");
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert(! $r['ready'] && ! $r['applied'] && $canonicalCount() === 0, 'Any additional missing tuple must block.');
$p->exec('DELETE FROM job_seeker_profiles WHERE id=3');
$tests[] = 'new_missing_blocks';
$p->exec("UPDATE job_seeker_profiles SET cv_path='cvs/different-missing.pdf' WHERE id=2");
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert(! $r['ready'] && ! $r['applied'] && $canonicalCount() === 0, 'Same missing count with a different exact tuple must block.');
$p->exec("UPDATE job_seeker_profiles SET cv_path='cvs/already-missing.pdf' WHERE id=2");
$tests[] = 'same_count_different_missing_tuple_blocks';
$p->exec('UPDATE job_seeker_profiles SET cv_path=NULL WHERE id=2');
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert(! $r['ready'] && ! $r['applied'] && $canonicalCount() === 0, 'A subset or removed original missing tuple must also require review.');
$p->exec("UPDATE job_seeker_profiles SET cv_path='cvs/already-missing.pdf' WHERE id=2");
$tests[] = 'missing_subset_blocks';
$p->exec('UPDATE job_seeker_profiles SET user_id=4 WHERE id=2');
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert(! $r['ready'] && ! $r['applied'] && $canonicalCount() === 0, 'Missing-reference ownership drift must invalidate consent.');
$p->exec('UPDATE job_seeker_profiles SET user_id=3 WHERE id=2');
$p->exec('UPDATE job_seeker_profiles SET user_id=4 WHERE id=1');
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert(! $r['ready'] && ! $r['applied'] && $canonicalCount() === 0, 'Present-reference ownership drift must also invalidate consent.');
$p->exec('UPDATE job_seeker_profiles SET user_id=1 WHERE id=1');
$tests[] = 'owner_drift_blocks';

$conflictId = $makeRun($tags[1], [['public', 'cvs/present.pdf', '%PDF different content', 'application/pdf']], $refs());
$r = $rescue->reconcile([$ids[0], $conflictId], $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert(! $r['ready'] && ! $r['applied'] && $r['counts']['conflicts'] === 1 && $canonicalCount() === 0, 'Same-path conflicts still block.');
$tests[] = 'conflict_blocks';
$unsupportedId = $makeRun($tags[1], [['public', 'cvs/present.pdf', '%PDF-1.4 synthetic existing CV', 'application/pdf'], ['public', 'company-logos/unsafe.png', 'not an image', 'text/plain']], $refs());
$p->exec("INSERT INTO company_profiles VALUES(1,2,'company-logos/unsafe.png')");
$unsupportedPlan = $rescue->planMissingBaseline($ids, $tags, $refs());
$r = $rescue->reconcile([$ids[0], $unsupportedId], $tags, $refs(), true, $ids, $unsupportedPlan['sha256'], true);
$assert(! $r['ready'] && ! $r['applied'] && $r['counts']['unsupported_references'] === 1 && $canonicalCount() === 0, 'Unsupported available file must block.');
$p->exec('DELETE FROM company_profiles WHERE id=1');
$tests[] = 'unsupported_available_file_blocks';

$beforeApplyBusiness = $business();
$writer = new PDO('pgsql:host=127.0.0.1;port=55432;dbname='.$database.';sslmode=disable', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$writer->exec("SET lock_timeout='150ms';SET statement_timeout='1s'");
$p->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ReviewOwnerProbeStatement::class]);
$writerBlocked = false;
ReviewOwnerProbeStatement::$afterOwnerRead = static function () use ($writer, &$writerBlocked, $assert): void {
    try {
        $writer->exec('UPDATE job_seeker_profiles SET user_id=4 WHERE id=1');
    } catch (PDOException $error) {
        $writerBlocked = $error->getCode() === '55P03';
    }
    $assert($writerBlocked, 'A real independent writer must time out while target ownership is already loaded.');
};
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert($writerBlocked && ReviewOwnerProbeStatement::$afterOwnerRead === null, 'The competing-writer test must execute during the real mapping transaction.');
$writer->exec('UPDATE job_seeker_profiles SET user_id=4 WHERE id=1');
$assert((int) $p->query('SELECT user_id FROM job_seeker_profiles WHERE id=1')->fetchColumn() === 4, 'Reference locks must release after commit.');
$writer->exec('UPDATE job_seeker_profiles SET user_id=1 WHERE id=1');
$tests[] = 'real_competing_owner_writer_blocked_then_released';
$assert($r['ready'] && $r['applied'] && $r['logical_files'] === 1, 'Exact explicit consent may map only available referenced CV.');
$assert($business() === $beforeApplyBusiness && $business() === $initialBusiness, 'All original references and owners must remain unchanged.');
$assert($files->metadata('documents', 'cvs/already-missing.pdf') === null, 'No fabricated missing file.');
$assert($files->metadata('documents', 'cvs/present.pdf')['owner_user_id'] === 1, 'Mapped CV must retain its exact owner.');
$read = $files->readStream('documents', 'cvs/present.pdf');
$assert(stream_get_contents($read) === $baseEntries[0][2], 'Canonical bytes must equal the actual preserved source.');
fclose($read);
$assert($savedCount() >= $initialSaved, 'All initial and conflicting preserved copies must remain.');
foreach ($initialPreserved as $original) {
    $metadata = $files->metadata($original['disk'], $original['path']);
    $assert($metadata !== null && (int) $metadata['size'] === (int) $original['size'] && $metadata['sha256'] === $original['sha256'], 'Each original preserved key and metadata must remain.');
    $originalStream = $files->readStream($original['disk'], $original['path']);
    $originalHash = hash_init('sha256');
    $originalBytes = hash_update_stream($originalHash, $originalStream);
    fclose($originalStream);
    $assert($originalBytes === (int) $original['size'] && hash_final($originalHash) === $original['sha256'], 'Each original preserved blob must retain its actual bytes.');
}
foreach ($initialManifests as $originalId => $originalJson) {
    $originalQuery = $p->prepare('SELECT manifest FROM stored_file_import_runs WHERE id=?');
    $originalQuery->execute([$originalId]);
    $assert($originalQuery->fetchColumn() === $originalJson, 'Every original manifest ID and exact JSONB representation must remain.');
}
$assert((int) $p->query('SELECT count(*) FROM stored_file_import_runs')->fetchColumn() === 4, 'Original and later snapshot manifests must remain.');
$tests[] = 'known_missing_retained_available_copy_mapped';
$canonicalBefore = $canonicalCount();
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert($r['applied'] && $canonicalCount() === $canonicalBefore && $business() === $initialBusiness, 'Explicit approved repeat must be idempotent.');
$tests[] = 'idempotent_repeat';

$p->exec('UPDATE stored_files SET owner_user_id=4 WHERE disk=\'documents\'');
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert(! $r['ready'] && ! $r['applied'] && $r['counts']['ownership_conflicts'] === 1, 'Existing canonical owner conflict must still block.');
$p->exec('UPDATE stored_files SET owner_user_id=1 WHERE disk=\'documents\'');
$tests[] = 'canonical_owner_conflict_blocks';
$q = $p->prepare('SELECT manifest FROM stored_file_import_runs WHERE id=?');
$q->execute([$ids[0]]);
$originalManifest = $q->fetchColumn();
$changed = json_decode($originalManifest, true);
$changed['created_at'] = '2026-10-29T01:00:00+00:00';
$q = $p->prepare('UPDATE stored_file_import_runs SET manifest=? WHERE id=?');
$q->execute([json_encode($changed), $ids[0]]);
$r = $rescue->reconcile($ids, $tags, $refs(), true, $ids, $plan['sha256'], true);
$assert(! $r['ready'] && ! $r['applied'], 'Tampering with a pinned original manifest must invalidate consent.');
$q->execute([$originalManifest, $ids[0]]);
$tests[] = 'original_manifest_drift_blocks';
$assert($business() === $initialBusiness, 'Final business records must exactly match their original snapshot.');
echo json_encode(['status' => 'local_review_prototype_passed', 'postgresql' => $p->query('SHOW server_version')->fetchColumn(),
    'database' => $database, 'tests' => count($tests), 'assertions' => $assertions, 'cases' => $tests,
    'production_accessed' => false, 'production_consent_recorded' => false, 'cutover_complete' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),PHP_EOL;
