<?php

namespace Tests\Unit;

use App\Storage\StoredFileRepository;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StoredFileRepositoryTest extends TestCase
{
    public function test_database_error_details_cannot_leak_blob_or_original_filename(): void
    {
        $pdo = $this->createStub(PDO::class);
        $statement = $this->createStub(PDOStatement::class);
        $pdo->method('prepare')->willReturn($statement);
        $statement->method('bindValue')->willReturn(true);
        $statement->method('execute')->willThrowException(new PDOException('Failing row: private-original.pdf PRIVATE-BLOB-CONTENTS'));
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'PRIVATE-BLOB-CONTENTS');
        rewind($stream);
        try {
            (new StoredFileRepository($pdo))->put('documents', 'cvs/test.pdf', $stream, ['original_name' => 'private-original.pdf']);
            $this->fail('Expected sanitized write failure.');
        } catch (RuntimeException $error) {
            $this->assertSame(RuntimeException::class, $error::class);
            $this->assertSame('Database file write failed.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        } finally {
            fclose($stream);
        }
    }
}
