<?php

use App\Storage\StoredFileSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        StoredFileSchema::create(DB::connection()->getPdo());
    }

    public function down(): void
    {
        // File data must never be dropped as a side effect of code rollback.
        throw new RuntimeException('Stored files require an explicit reviewed recovery/export procedure.');
    }
};
