<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->uuid('cv_analysis_attempt_id')->nullable()->index();
            $table->string('cv_analysis_error_code', 64)->nullable();
            $table->text('cv_analysis_error_message')->nullable();
            $table->timestamp('cv_analysis_started_at')->nullable();
            $table->timestamp('cv_analysis_completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropIndex(['cv_analysis_attempt_id']);
            $table->dropColumn([
                'cv_analysis_attempt_id',
                'cv_analysis_error_code',
                'cv_analysis_error_message',
                'cv_analysis_started_at',
                'cv_analysis_completed_at',
            ]);
        });
    }
};
