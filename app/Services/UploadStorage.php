<?php

namespace App\Services;

use App\Exceptions\OpenAiException;
use App\Storage\StoredFileSchema;
use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UploadStorage
{
    public const MEDIA_DIRECTORIES = ['company-logos', 'training-providers', 'training-courses', 'homepage-advertisements'];

    public static function safePath(mixed $path, array $directories): bool
    {
        if (! is_string($path) || $path === '' || $path !== trim($path)
            || preg_match('/[\x00-\x1f\x7f\\\\%]/', $path)) {
            return false;
        }

        $segments = explode('/', $path);

        return count($segments) === 2 && in_array($segments[0], $directories, true)
            && (bool) preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/', $segments[1]);
    }

    public static function mediaUrl(string $path): string
    {
        return self::safePath($path, self::MEDIA_DIRECTORIES)
            ? route('uploads.media', ['path' => $path], absolute: false) : '';
    }

    public static function cvMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    public static function maxKilobytes(string $kind): int
    {
        return min(65536, max(1, (int) config('uploads.limits_kb.'.$kind, 2048)));
    }

    public static function options(UploadedFile $file, int $owner): array
    {
        return ['visibility' => 'private', 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'owner_user_id' => $owner];
    }

    public function assertDatabaseConfiguration(): void
    {
        foreach (['public' => 'public', 'documents' => 'documents'] as $name => $prefix) {
            $disk = config('filesystems.disks.'.$name);
            if (($disk['driver'] ?? null) !== 'database' || ($disk['namespace'] ?? null) !== $prefix
                || ($disk['visibility'] ?? null) !== 'private') {
                throw new RuntimeException('Private database storage is not configured.');
            }
        }

        if (config('uploads.legacy_cv_reads')) {
            throw new RuntimeException('Legacy public CV reads must be disabled for release.');
        }
        StoredFileSchema::verifyWithLedger(DB::connection()->getPdo());
    }

    public function documents(): Filesystem
    {
        if (app()->isProduction()) {
            $this->assertDatabaseConfiguration();
        }

        return Storage::disk('documents');
    }

    public function media(): Filesystem
    {
        if (app()->isProduction()) {
            $this->assertDatabaseConfiguration();
        }

        return Storage::disk('public');
    }

    public function cvDisk(string $path): ?Filesystem
    {
        if (! self::safePath($path, ['cvs'])) {
            return null;
        }

        $documents = $this->documents();
        if ($documents->exists($path)) {
            return $documents;
        }

        if (! app()->isProduction() && config('uploads.legacy_cv_reads') && Storage::disk('legacy_public')->exists($path)) {
            return Storage::disk('legacy_public');
        }

        return null;
    }

    /** Materialize only for the AI client's local-file API; never log the path. */
    public function withCvFile(string $path, Closure $callback): mixed
    {
        $disk = $this->cvDisk($path);
        if (! $disk) {
            throw OpenAiException::missingFile();
        }

        $source = $disk->readStream($path);
        $temporary = tmpfile();
        if (! is_resource($source) || ! is_resource($temporary)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($temporary)) {
                fclose($temporary);
            }
            throw new RuntimeException('Cannot open CV stream.');
        }

        try {
            $limit = self::maxKilobytes('cv') * 1024;
            $bytes = stream_copy_to_stream($source, $temporary, $limit + 1);
            if ($bytes === false || $bytes < 1 || $bytes > $limit) {
                throw OpenAiException::invalidRequest();
            }
            fflush($temporary);
            $localPath = stream_get_meta_data($temporary)['uri'];
            chmod($localPath, 0600);

            return $callback($localPath);
        } finally {
            fclose($source);
            fclose($temporary);
        }
    }
}
