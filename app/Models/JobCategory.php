<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCategory extends Model
{
    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'sort_order',
    ];

    public function subcategories(): HasMany
    {
        return $this->hasMany(JobSubcategory::class, 'category_key', 'key')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'category', 'key');
    }

    public function linkedJobsQuery(): Builder
    {
        $subcategoryKeys = $this->subcategories()->pluck('key');

        return Job::query()->where(function (Builder $query) use ($subcategoryKeys): void {
            $query->where('category', $this->key);

            if ($subcategoryKeys->isNotEmpty()) {
                $query->orWhereIn('subcategory', $subcategoryKeys);
            }
        });
    }
}
