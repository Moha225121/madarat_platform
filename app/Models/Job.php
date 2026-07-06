<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    /** @use HasFactory<\Database\Factories\JobFactory> */
    use HasFactory;

    protected $fillable = [
        'company_profile_id',
        'title',
        'slug',
        'description',
        'responsibilities',
        'required_skills',
        'location',
        'job_type',
        'contract_type',
        'experience_level',
        'salary_min',
        'salary_max',
        'status',
        'generated_description',
    ];

    protected function casts(): array
    {
        return [
            'responsibilities' => 'array',
            'required_skills' => 'array',
            'generated_description' => 'boolean',
        ];
    }

    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
