<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseRecommendation extends Model
{
    protected $fillable = ['job_seeker_id', 'course_id', 'score', 'missing_skills_covered', 'target_job_ids', 'evidence', 'reason', 'confidence', 'content_signature', 'recommended_at', 'dismissed_at', 'saved_at'];

    protected function casts(): array
    {
        return ['missing_skills_covered' => 'array', 'target_job_ids' => 'array', 'evidence' => 'array', 'recommended_at' => 'datetime', 'dismissed_at' => 'datetime', 'saved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'job_seeker_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }
}
