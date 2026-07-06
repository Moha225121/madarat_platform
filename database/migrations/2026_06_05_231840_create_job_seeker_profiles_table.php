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
        Schema::create('job_seeker_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->nullable();
            $table->string('city')->nullable();
            $table->string('field')->nullable();
            $table->text('bio')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_status')->default('pending');
            $table->integer('profile_score')->default(0);
            $table->json('extracted_skills')->nullable();
            $table->json('missing_skills')->nullable();
            $table->text('education_summary')->nullable();
            $table->text('experience_summary')->nullable();
            $table->json('ai_recommendations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_seeker_profiles');
    }
};
