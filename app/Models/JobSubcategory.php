<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobSubcategory extends Model
{
    protected $fillable = [
        'category_key',
        'key',
        'name_ar',
        'name_en',
        'sort_order',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'category_key', 'key');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'subcategory', 'key');
    }
}
