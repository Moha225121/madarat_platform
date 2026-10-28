<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

class MigrateJobCategories extends Command
{
    use ConfirmableTrait;

    public const LOCK_NAMESPACE = 190733;

    public const LOCK_KEY = 20261022;

    protected $signature = 'madarat:migrate-job-categories {--force : Run without the production confirmation prompt}';

    protected $description = 'Run only the job-category migrations under a PostgreSQL session lock';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('This deployment command requires PostgreSQL. No migrations were run.');

            return self::FAILURE;
        }

        $bindings = [self::LOCK_NAMESPACE, self::LOCK_KEY];
        $lock = $connection->selectOne('SELECT pg_try_advisory_lock(?, ?) AS acquired', $bindings);

        if (! $lock->acquired) {
            $this->error('Another category migration process holds the lock. No migrations were run; stop this deployment and retry after it finishes.');

            return self::FAILURE;
        }

        try {
            return $this->call('migrate', [
                '--database' => $connection->getName(),
                '--path' => [
                    'database/migrations/2026_10_22_000001_add_category_fields_to_jobs_table.php',
                    'database/migrations/2026_10_22_000002_create_job_category_tables.php',
                ],
                '--force' => true,
            ]);
        } finally {
            $connection->selectOne('SELECT pg_advisory_unlock(?, ?) AS released', $bindings);
        }
    }
}
