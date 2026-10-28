<?php

namespace App\Support;

use App\Models\JobCategory;
use App\Models\JobSubcategory;

class JobCategoryCatalog
{
    /** @var array<string, array{label_ar: string, label_en: string, subcategories: array<string, array{label_ar: string, label_en: string}>}>|null */
    private ?array $definitions = null;

    /** @return array<string, array{label_ar: string, label_en: string, subcategories: array<string, array{label_ar: string, label_en: string}>}> */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $this->definitions = JobCategory::query()
            ->with('subcategories')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (JobCategory $category): array => [
                $category->key => [
                    'label_ar' => $category->name_ar,
                    'label_en' => $category->name_en ?: $category->name_ar,
                    'subcategories' => $category->subcategories
                        ->mapWithKeys(fn (JobSubcategory $subcategory): array => [
                            $subcategory->key => [
                                'label_ar' => $subcategory->name_ar,
                                'label_en' => $subcategory->name_en ?: $subcategory->name_ar,
                            ],
                        ])
                        ->all(),
                ],
            ])
            ->all();

        return $this->definitions;
    }

    /** @return array<int, string> */
    public function categoryKeys(): array
    {
        return array_keys($this->all());
    }

    /** @return array<int, string> */
    public function subcategoryKeys(?string $category = null): array
    {
        if ($category !== null) {
            return array_keys($this->all()[$category]['subcategories'] ?? []);
        }

        return collect($this->all())
            ->flatMap(fn (array $definition): array => array_keys($definition['subcategories']))
            ->values()
            ->all();
    }

    public function hasCategory(?string $category): bool
    {
        return $category !== null && array_key_exists($category, $this->all());
    }

    public function hasSubcategory(?string $category, ?string $subcategory): bool
    {
        return $category !== null
            && $subcategory !== null
            && array_key_exists($subcategory, $this->all()[$category]['subcategories'] ?? []);
    }

    public function categoryLabel(?string $category, string $locale = 'ar'): ?string
    {
        if (! $this->hasCategory($category)) {
            return null;
        }

        return $this->all()[$category][$locale === 'en' ? 'label_en' : 'label_ar'];
    }

    public function subcategoryLabel(?string $category, ?string $subcategory, string $locale = 'ar'): ?string
    {
        if (! $this->hasSubcategory($category, $subcategory)) {
            return null;
        }

        return $this->all()[$category]['subcategories'][$subcategory][$locale === 'en' ? 'label_en' : 'label_ar'];
    }

    /**
     * @param  array<string, int>  $categoryCounts
     * @param  array<string, int>  $subcategoryCounts
     * @return array<int, array{key: string, label: string, label_en: string, count: int, subcategories: array<int, array{key: string, label: string, label_en: string, count: int}>}>
     */
    public function forFrontend(array $categoryCounts = [], array $subcategoryCounts = []): array
    {
        return collect($this->all())
            ->map(function (array $definition, string $key) use ($categoryCounts, $subcategoryCounts): array {
                return [
                    'key' => $key,
                    'label' => $definition['label_ar'],
                    'label_en' => $definition['label_en'],
                    'count' => (int) ($categoryCounts[$key] ?? 0),
                    'subcategories' => collect($definition['subcategories'])
                        ->map(fn (array $subcategory, string $subcategoryKey): array => [
                            'key' => $subcategoryKey,
                            'label' => $subcategory['label_ar'],
                            'label_en' => $subcategory['label_en'],
                            'count' => (int) ($subcategoryCounts[$subcategoryKey] ?? 0),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
