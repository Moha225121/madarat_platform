<?php

namespace App\Storage;

use PDO;
use RuntimeException;
use Throwable;

/** PDO is deliberate: binary bindings never enter Laravel's query/error logs. */
final class StoredFileRepository
{
    public const HARD_MAX_BYTES = 67108864;

    public const METADATA = 'id,disk,path,original_name,mime_type,size,sha256,access,owner_user_id,created_at,updated_at';

    public function __construct(private PDO $pdo) {}

    public function metadata(string $disk, string $path): ?array
    {
        $query = $this->pdo->prepare('SELECT '.self::METADATA.' FROM stored_files WHERE disk=? AND path=?');
        $query->execute([$disk, $path]);
        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @param resource $source */
    public function put(string $disk, string $path, $source, array $metadata = [], int $maxBytes = self::HARD_MAX_BYTES): bool
    {
        if (! in_array($disk, ['public', 'documents', 'preserved'], true) || strlen($path) > 512
            || $path === '' || str_contains($path, "\0") || ! is_resource($source)) {
            throw new RuntimeException('Invalid stored-file input.');
        }
        $maxBytes = min(max(1, $maxBytes), self::HARD_MAX_BYTES);
        $buffer = tmpfile();
        if (! $buffer) {
            throw new RuntimeException('Cannot buffer file.');
        }
        try {
            $bytes = 0;
            $hash = hash_init('sha256');
            while (! feof($source)) {
                $chunk = fread($source, 65536);
                if ($chunk === false) {
                    throw new RuntimeException('Cannot read source.');
                }
                $bytes += strlen($chunk);
                if ($bytes > $maxBytes) {
                    throw new RuntimeException('File exceeds configured size limit.');
                }
                hash_update($hash, $chunk);
                if (fwrite($buffer, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Incomplete file buffer.');
                }
            }
            if ($bytes < 1 && $disk !== 'preserved') {
                throw new RuntimeException('Empty upload.');
            }
            $digest = hash_final($hash);
            rewind($buffer);
            $originalName = basename(str_replace('\\', '/', (string) ($metadata['original_name'] ?? basename($path))));
            $originalName = preg_replace('/[\x00-\x1f\x7f]/u', '', mb_scrub($originalName, 'UTF-8'));
            $originalName = mb_substr($originalName ?: 'file', 0, 255);
            $mime = (string) ($metadata['mime_type'] ?? 'application/octet-stream');
            if (! preg_match('~\A[a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+\z~', $mime)) {
                $mime = 'application/octet-stream';
            }
            $statement = $this->pdo->prepare('INSERT INTO stored_files
                (disk,path,original_name,mime_type,size,sha256,access,owner_user_id,contents)
                VALUES (:disk,:path,:name,:mime,:size,:sha,:access,:owner,:contents)
                ON CONFLICT (disk,path) DO NOTHING');
            foreach (['disk' => $disk, 'path' => $path, 'name' => $originalName, 'mime' => $mime,
                'sha' => $digest, 'access' => $disk === 'public' ? 'public' : 'private'] as $key => $value) {
                $statement->bindValue(':'.$key, $value, PDO::PARAM_STR);
            }
            $statement->bindValue(':size', $bytes, PDO::PARAM_INT);
            $owner = $metadata['owner_user_id'] ?? null;
            $statement->bindValue(':owner', $owner, $owner === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue(':contents', $buffer, PDO::PARAM_LOB);
            try {
                $statement->execute();
            } catch (\PDOException) {
                // PostgreSQL DETAIL may include a failing row's blob/name.
                // Do not attach that exception to application error logs.
                throw new RuntimeException('Database file write failed.');
            }
            $created = $statement->rowCount() === 1;
            $row = $this->metadata($disk, $path);
            if (! $row || $row['sha256'] !== $digest || (int) $row['size'] !== $bytes) {
                throw new RuntimeException('File conflict: existing bytes will not be overwritten.');
            }
            $verified = $this->readStream($disk, $path);
            fclose($verified);

            return $created;
        } finally {
            fclose($buffer);
        }
    }

    /** @return resource */
    public function readStream(string $disk, string $path)
    {
        $query = $this->pdo->prepare('SELECT contents,size,sha256 FROM stored_files WHERE disk=? AND path=?');
        $query->execute([$disk, $path]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (! $row || (int) $row['size'] > self::HARD_MAX_BYTES) {
            throw new RuntimeException('File missing or too large.');
        }
        $stream = tmpfile();
        if (! $stream) {
            throw new RuntimeException('Cannot open file stream.');
        }
        $source = $row['contents'];
        try {
            if (is_resource($source)) {
                $bytes = stream_copy_to_stream($source, $stream, self::HARD_MAX_BYTES + 1);
            } else {
                $bytes = fwrite($stream, $source);
            }
            rewind($stream);
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            if ($bytes !== (int) $row['size'] || hash_final($hash) !== $row['sha256']) {
                throw new RuntimeException('Stored-file integrity verification failed.');
            }
            rewind($stream);

            return $stream;
        } catch (Throwable $error) {
            fclose($stream);
            throw $error;
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }
    }

    public function delete(string $disk, string $path): void
    {
        $statement = $this->pdo->prepare('DELETE FROM stored_files WHERE disk=? AND path=?');
        $statement->execute([$disk, $path]);
    }

    public function listing(string $disk, string $prefix): array
    {
        $query = $this->pdo->prepare('SELECT '.self::METADATA.' FROM stored_files WHERE disk=? ORDER BY path');
        $query->execute([$disk]);

        return array_values(array_filter($query->fetchAll(PDO::FETCH_ASSOC), fn ($row) => $prefix === '' || str_starts_with($row['path'], rtrim($prefix, '/').'/')));
    }
}
