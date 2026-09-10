<?php

namespace App\Models;

use Database\Factories\JobSeekerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSeekerProfile extends Model
{
    /** @use HasFactory<JobSeekerProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'headline',
        'city',
        'field',
        'bio',
        'cv_path',
        'cv_status',
        'cv_analysis_attempt_id',
        'cv_analysis_error_code',
        'cv_analysis_error_message',
        'cv_analysis_started_at',
        'cv_analysis_completed_at',
        'profile_score',
        'extracted_skills',
        'missing_skills',
        'education_summary',
        'experience_summary',
        'ai_recommendations',
    ];

    protected function casts(): array
    {
        return [
            'extracted_skills' => 'array',
            'missing_skills' => 'array',
            'ai_recommendations' => 'array',
            'cv_analysis_started_at' => 'datetime',
            'cv_analysis_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
