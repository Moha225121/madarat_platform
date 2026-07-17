<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingCourse extends Model
{
    use HasFactory;

    protected $fillable = ['training_provider_id', 'title', 'slug', 'short_description', 'description', 'learning_outcomes', 'skills_taught', 'target_audience', 'prerequisites', 'difficulty_level', 'delivery_method', 'city', 'location', 'is_remote', 'duration_value', 'duration_unit', 'start_date', 'end_date', 'registration_deadline', 'price', 'currency', 'capacity', 'contact_email', 'contact_phone', 'registration_url', 'cover_image_path', 'certificate_available', 'status', 'submitted_at', 'published_at', 'reviewed_at', 'reviewed_by', 'rejection_reason', 'audience_analysis', 'analysis_model', 'analysis_content_hash', 'analyzed_at'];

    protected function casts(): array
    {
        return ['learning_outcomes' => 'array', 'skills_taught' => 'array', 'prerequisites' => 'array', 'audience_analysis' => 'array', 'is_remote' => 'boolean', 'certificate_available' => 'boolean', 'price' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date', 'registration_deadline' => 'date', 'submitted_at' => 'datetime', 'published_at' => 'datetime', 'reviewed_at' => 'datetime', 'analyzed_at' => 'datetime'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TrainingProviderProfile::class, 'training_provider_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(CourseUserFeedback::class, 'course_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(CourseRecommendation::class, 'course_id');
    }

    public function analysisIsStale(): bool
    {
        return $this->analysis_content_hash !== $this->contentHash();
    }

    public function contentHash(): string
    {
        return hash('sha256', json_encode($this->only(['title', 'description', 'skills_taught', 'learning_outcomes', 'difficulty_level', 'prerequisites', 'delivery_method', 'duration_value', 'duration_unit'])));
    }
}
