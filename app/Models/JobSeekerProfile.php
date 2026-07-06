<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSeekerProfile extends Model
{
    /** @use HasFactory<\Database\Factories\JobSeekerProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'headline',
        'city',
        'field',
        'bio',
        'cv_path',
        'cv_status',
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
