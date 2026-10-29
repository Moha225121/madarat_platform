<?php

namespace App\Console\Commands;

use App\Services\UploadStorage;
use Illuminate\Console\Command;
use Throwable;

class CheckUploadStorage extends Command
{
    protected $signature = 'uploads:check';

    protected $description = 'Read-only validation of PostgreSQL upload configuration, schema and migration ledger';

    public function handle(UploadStorage $storage): int
    {
        try {
            $storage->assertDatabaseConfiguration();
        } catch (Throwable) {
            $this->error('Not ready: check database disks, PostgreSQL schema/migration ledger and disable legacy CV reads. No secrets printed.');

            return self::FAILURE;
        }
        $this->info('Database configuration/schema ready. Imported file integrity and backups still require separate verification.');

        return self::SUCCESS;
    }
}
