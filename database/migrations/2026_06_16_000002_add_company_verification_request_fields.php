<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('company_profiles', 'verification_requested_at')) {
                $table->timestamp('verification_requested_at')->nullable()->after('verification_status');
            }

            if (! Schema::hasColumn('company_profiles', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_requested_at');
            }
        });

        DB::table('company_profiles')
            ->whereIn('verification_status', ['pending', 'approved'])
            ->update([
                'verification_status' => DB::raw("CASE WHEN verification_status = 'approved' THEN 'verified' ELSE 'unverified' END"),
                'verified_at' => DB::raw("CASE WHEN verification_status = 'approved' THEN CURRENT_TIMESTAMP ELSE verified_at END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('company_profiles', 'verified_at')) {
                $table->dropColumn('verified_at');
            }

            if (Schema::hasColumn('company_profiles', 'verification_requested_at')) {
                $table->dropColumn('verification_requested_at');
            }
        });
    }
};
