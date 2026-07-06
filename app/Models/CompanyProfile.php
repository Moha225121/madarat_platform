<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyProfile extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'industry',
        'headquarters',
        'logo_path',
        'description',
        'verification_status',
        'verification_requested_at',
        'verified_at',
    ];

    protected $casts = [
        'verification_requested_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }
}
