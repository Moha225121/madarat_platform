<?php

use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;

/** Standalone: copy this reviewed file into /tmp only AFTER backup approval. */
final class MadaratUploadSnapshot
{
    public static function create(string $source, string $destination, array $references = []): array
    {
        umask(0077);
        $source = realpath($source);
        $destination = realpath($destination);
        if (! $source || ! $destination || str_starts_with($destination.DIRECTORY_SEPARATOR, $source.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Snapshot destination must be outside the application.');
        }
        $tag = substr(hash('sha256', gethostname() ?: 'unknown'), 0, 12);
        $directory = $destination.'/madarat-uploads-'.gmdate('Ymd-His').'-'.$tag.'-'.bin2hex(random_bytes(16));
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create exclusive snapshot directory.');
        }
        $roots = ['public' => $source.'/storage/app/public', 'local' => $source.'/storage/app/private'];
        $entries = [];
        foreach ($roots as $disk => $root) {
            foreach (self::inventory($root) as $entry) {
                $target = $directory.'/files/'.$disk.'/'.$entry['path'];
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true)) {
                    throw new RuntimeException('Cannot create snapshot subdirectory.');
                }
                $input = fopen($root.'/'.$entry['path'], 'rb');
                $output = fopen($target, 'xb');
                try {
                    if (! $input || ! $output || stream_copy_to_stream($input, $output) !== $entry['bytes']) {
                        throw new RuntimeException('Snapshot copy incomplete.');
                    }
                } finally {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                }
                if (hash_file('sha256', $target) !== $entry['sha256']) {
                    throw new RuntimeException('Snapshot content changed during copy.');
                }
                $entries[] = ['disk' => $disk, ...$entry];
            }
        }
        $after = [];
        foreach ($roots as $disk => $root) {
            foreach (self::inventory($root) as $entry) {
                $after[] = ['disk' => $disk, ...$entry];
            }
        }
        if ($after !== $entries) {
            throw new RuntimeException('Source changed during snapshot; retry with a new snapshot.');
        }
        $manifest = ['format' => 1, 'instance_tag' => $tag, 'created_at' => gmdate(DATE_ATOM),
            'entries' => $entries, 'database_references' => $references];
        self::writeExclusive($directory.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $archive = new PharData($directory.'/snapshot.tar');
        $archive->addFile($directory.'/manifest.json', 'manifest.json');
        foreach ($entries as $entry) {
            $relative = 'files/'.$entry['disk'].'/'.$entry['path'];
            $archive->addFile($directory.'/'.$relative, $relative);
        }
        unset($archive);
        // Verify the archive's bytes, not only the staging copies.
        $archive = new PharData($directory.'/snapshot.tar');
        foreach ($entries as $entry) {
            $contents = $archive['files/'.$entry['disk'].'/'.$entry['path']]->getContent();
            if (strlen($contents) !== $entry['bytes'] || hash('sha256', $contents) !== $entry['sha256']) {
                throw new RuntimeException('Archive validation failed.');
            }
        }
        unset($archive);
        $digest = hash_file('sha256', $directory.'/snapshot.tar');
        self::writeExclusive($directory.'/snapshot.sha256', $digest."  snapshot.tar\n");

        return ['directory' => $directory, 'instance_tag' => $tag, 'files' => count($entries),
            'bytes' => array_sum(array_column($entries, 'bytes')), 'archive_sha256' => $digest,
            'references' => count($references), 'local_snapshot_only' => true];
    }

    public static function inventory(string $root): array
    {
        if (is_link($root) || ! is_dir($root)) {
            throw new RuntimeException('Missing or symlinked storage root; inventory incomplete.');
        }
        $result = [];
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($items as $file) {
            if ($file->isLink() || ! $file->isFile()) {
                throw new RuntimeException('Non-regular entry requires separate review.');
            }
            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
            $digest = hash_file('sha256', $file->getPathname());
            if ($digest === false) {
                throw new RuntimeException('Unreadable file; inventory incomplete.');
            }
            $result[] = ['path' => $path, 'bytes' => $file->getSize(), 'sha256' => $digest];
        }
        usort($result, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $result;
    }

    public static function writeExclusive(string $path, string $contents): void
    {
        if (file_exists($path) || is_link($path)) {
            throw new RuntimeException('Refusing to overwrite an existing report.');
        }
        $stream = @fopen($path, 'xb');
        if (! $stream) {
            throw new RuntimeException('Refusing to overwrite an existing report.');
        }
        try {
            if (fwrite($stream, $contents) !== strlen($contents)) {
                throw new RuntimeException('Incomplete report write.');
            }
        } finally {
            fclose($stream);
        }
    }

    public static function references(string $source): array
    {
        require_once $source.'/vendor/autoload.php';
        $app = require $source.'/bootstrap/app.php';
        (new LoadEnvironmentVariables)->bootstrap($app);
        (new LoadConfiguration)->bootstrap($app);
        (new DatabaseServiceProvider($app))->register();
        $connection = $app['db']->connection();

        return $connection->transaction(function () use ($connection): array {
            if ($connection->getDriverName() === 'pgsql') {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }
            $result = [];
            foreach (['job_seeker_profiles' => ['cv_path'], 'company_profiles' => ['logo_path'],
                'training_provider_profiles' => ['logo_path', 'profile_image_path'],
                'training_courses' => ['cover_image_path'], 'homepage_advertisements' => ['image_path']] as $table => $columns) {
                foreach ($columns as $column) {
                    foreach ($connection->table($table)->select('id', $column)->whereNotNull($column)->orderBy('id')->get() as $row) {
                        $result[] = ['table' => $table, 'id' => $row->id, 'column' => $column, 'disk' => 'public', 'path' => $row->{$column}];
                    }
                }
            }

            return $result;
        });
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');
    try {
        $options = getopt('', ['source:', 'destination:', 'with-references', 'approved-copy']);
        if (! isset($options['approved-copy'], $options['source'], $options['destination'])) {
            throw new RuntimeException('Explicit copy approval and paths are required.');
        }
        $references = isset($options['with-references']) ? MadaratUploadSnapshot::references($options['source']) : [];
        echo json_encode(MadaratUploadSnapshot::create($options['source'], $options['destination'], $references), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), PHP_EOL;
    } catch (Throwable) {
        // No paths, filenames, DB configuration or exception details in console/logs.
        fwrite(STDERR, "Snapshot incomplete. Keep originals; inspect only in an authorized private session.\n");
        exit(1);
    }
}
