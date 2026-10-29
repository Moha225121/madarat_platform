<?php

namespace App\Storage;

use App\Services\UploadStorage;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use RuntimeException;
use Throwable;

final class DatabaseFilesystemAdapter implements FilesystemAdapter
{
    public function __construct(private StoredFileRepository $files, private string $disk) {}

    private function allowed(string $path): bool
    {
        return UploadStorage::safePath($path, $this->disk === 'documents' ? ['cvs'] : UploadStorage::MEDIA_DIRECTORIES);
    }

    public function fileExists(string $path): bool
    {
        return $this->allowed($path) && $this->files->metadata($this->disk, $path) !== null;
    }

    public function ownedBy(string $path, int $userId): bool
    {
        $row = $this->allowed($path) ? $this->files->metadata($this->disk, $path) : null;

        return $row && $row['owner_user_id'] !== null && (int) $row['owner_user_id'] === $userId;
    }

    public function originalName(string $path): string
    {
        return $this->files->metadata($this->disk, $path)['original_name'] ?? basename($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->files->listing($this->disk, $path) !== [];
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $stream = fopen('php://temp', 'w+b');
        try {
            fwrite($stream, $contents);
            rewind($stream);
            $this->writeStream($path, $stream, $config);
        } finally {
            fclose($stream);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            if (! $this->allowed($path) || $config->get('visibility', Visibility::PRIVATE) !== Visibility::PRIVATE) {
                throw new RuntimeException('Unsupported file path or visibility.');
            }
            $kind = $this->disk === 'documents' ? 'cv' : match (explode('/', $path)[0]) {
                'homepage-advertisements' => 'advertisement', 'training-courses' => 'course', default => 'image',
            };
            $max = UploadStorage::maxKilobytes($kind) * 1024;
            $this->files->put($this->disk, $path, $contents, [
                'original_name' => $config->get('original_name', basename($path)),
                'mime_type' => $config->get('mime_type', UploadStorage::cvMimeType($path)),
                'owner_user_id' => $config->get('owner_user_id'),
            ], $max);
        } catch (Throwable $error) {
            throw UnableToWriteFile::atLocation($path, 'Database upload failed.', $error);
        }
    }

    public function read(string $path): string
    {
        $stream = $this->readStream($path);
        try {
            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    public function readStream(string $path)
    {
        try {
            if (! $this->allowed($path)) {
                throw new RuntimeException('Unsupported file path.');
            }

            return $this->files->readStream($this->disk, $path);
        } catch (Throwable $error) {
            throw UnableToReadFile::fromLocation($path, 'Database read failed.', $error);
        }
    }

    public function delete(string $path): void
    {
        if (! $this->allowed($path)) {
            throw new RuntimeException('Unsupported file path.');
        }
        $this->files->delete($this->disk, $path);
    }

    public function deleteDirectory(string $path): void
    {
        foreach ($this->listContents($path, true) as $file) {
            $this->delete($file->path());
        }
    }

    public function createDirectory(string $path, Config $config): void {}

    public function setVisibility(string $path, string $visibility): void
    {
        if ($visibility !== Visibility::PRIVATE) {
            throw new RuntimeException('Objects are never directly public.');
        }
    }

    private function attributes(string $path): FileAttributes
    {
        $row = $this->allowed($path) ? $this->files->metadata($this->disk, $path) : null;
        if (! $row) {
            throw UnableToReadFile::fromLocation($path);
        }

        return new FileAttributes($path, (int) $row['size'], Visibility::PRIVATE, strtotime($row['updated_at']), $row['mime_type']);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->attributes($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->attributes($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->attributes($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->attributes($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        foreach ($this->files->listing($this->disk, $path) as $row) {
            if ($this->allowed($row['path']) && ($deep || ! str_contains(substr($row['path'], strlen($path === '' ? '' : rtrim($path, '/').'/')), '/'))) {
                yield new FileAttributes($row['path'], (int) $row['size'], Visibility::PRIVATE, strtotime($row['updated_at']), $row['mime_type']);
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $stream = $this->readStream($source);
        try {
            $this->writeStream($destination, $stream, $config);
        } finally {
            fclose($stream);
        }
    }
}
