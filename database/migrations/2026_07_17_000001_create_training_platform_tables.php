<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider_type')->default('company')->index();
            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('profile_image_path')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('address')->nullable();
            $table->json('specializations')->nullable();
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->string('commercial_registration_number')->nullable();
            $table->json('certifications')->nullable();
            $table->string('verification_status')->default('incomplete')->index();
            $table->timestamp('verification_requested_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_provider_id')->constrained('training_provider_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description');
            $table->json('learning_outcomes')->nullable();
            $table->json('skills_taught')->nullable();
            $table->text('target_audience')->nullable();
            $table->json('prerequisites')->nullable();
            $table->string('difficulty_level')->index();
            $table->string('delivery_method')->index();
            $table->string('city')->nullable()->index();
            $table->string('location')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->unsignedInteger('duration_value')->nullable();
            $table->string('duration_unit')->nullable();
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable();
            $table->date('registration_deadline')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('LYD');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('registration_url')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->boolean('certificate_available')->default(false)->index();
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->json('audience_analysis')->nullable();
            $table->string('analysis_model')->nullable();
            $table->string('analysis_content_hash', 64)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
            $table->index(['training_provider_id', 'status']);
        });

        Schema::create('course_user_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->boolean('saved')->default(false);
            $table->boolean('interested')->default(false);
            $table->boolean('completed')->default(false);
            $table->boolean('already_knows')->default(false);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
            $table->index(['user_id', 'saved']);
        });

        Schema::create('course_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->json('missing_skills_covered');
            $table->json('target_job_ids')->nullable();
            $table->json('evidence');
            $table->text('reason');
            $table->decimal('confidence', 4, 3)->default(0);
            $table->string('content_signature', 64);
            $table->timestamp('recommended_at');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
            $table->unique(['job_seeker_id', 'course_id']);
            $table->index(['job_seeker_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_recommendations');
        Schema::dropIfExists('course_user_feedback');
        Schema::dropIfExists('training_courses');
        Schema::dropIfExists('training_provider_profiles');
    }
};
