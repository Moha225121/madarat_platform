<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProviderProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'provider_type', 'display_name', 'legal_name', 'description', 'logo_path', 'profile_image_path', 'email', 'phone', 'website', 'city', 'address', 'specializations', 'years_of_experience', 'commercial_registration_number', 'certifications', 'verification_status', 'verification_requested_at', 'verified_at', 'verified_by', 'rejection_reason'];

    protected function casts(): array
    {
        return ['specializations' => 'array', 'certifications' => 'array', 'verification_requested_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(TrainingCourse::class, 'training_provider_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }
}
