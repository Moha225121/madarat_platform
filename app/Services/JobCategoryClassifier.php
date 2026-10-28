<?php

namespace App\Services;

use App\Support\JobCategoryCatalog;

class JobCategoryClassifier
{
    public function __construct(private readonly JobCategoryCatalog $catalog) {}

    /**
     * Classify only high-confidence matches. Ambiguous jobs deliberately remain
     * uncategorized so they continue to appear under "وظائف أخرى".
     *
     * @param  array<int, string>|string|null  $responsibilities
     * @return array{category: string, subcategory: string}|null
     */
    public function classify(string $title, array|string|null $responsibilities = null): ?array
    {
        $responsibilityText = is_array($responsibilities)
            ? implode(' ', $responsibilities)
            : (string) $responsibilities;
        $text = $this->normalize("{$title} {$responsibilityText}");

        foreach ($this->rules() as $rule) {
            if ($this->containsAny($text, $rule['phrases'])
                && $this->catalog->hasSubcategory($rule['category'], $rule['subcategory'])) {
                return [
                    'category' => $rule['category'],
                    'subcategory' => $rule['subcategory'],
                ];
            }
        }

        return null;
    }

    /**
     * More specific phrases intentionally come first. In particular, delivery
     * representatives must never be grouped with promotion or external sales.
     *
     * @return array<int, array{category: string, subcategory: string, phrases: array<int, string>}>
     */
    private function rules(): array
    {
        return [
            [
                'category' => 'transport_delivery',
                'subcategory' => 'delivery_representatives',
                'phrases' => ['مندوب توصيل', 'مندوب للتوصيل', 'سائق توصيل', 'عامل توصيل', 'توصيل الطلبات'],
            ],
            [
                'category' => 'medical',
                'subcategory' => 'pharmaceutical_companies',
                'phrases' => ['مندوب طبي', 'مندوب ادويه', 'شركه ادويه', 'شركات الادويه'],
            ],
            [
                'category' => 'marketing_sales',
                'subcategory' => 'promotion_representatives',
                'phrases' => ['مندوب ترويج', 'مندوب مبيعات', 'مندوب بيع', 'مندوب تسويق', 'مروج مبيعات', 'مروج منتجات'],
            ],
            [
                'category' => 'marketing_sales',
                'subcategory' => 'digital_marketing',
                'phrases' => ['تسويق رقمي', 'التسويق الرقمي', 'مسوق رقمي', 'اعلانات رقميه', 'seo'],
            ],
            [
                'category' => 'marketing_sales',
                'subcategory' => 'sales',
                'phrases' => ['موظف مبيعات', 'مسؤول مبيعات', 'اخصائي مبيعات', 'مدير مبيعات'],
            ],
            [
                'category' => 'content_design_media',
                'subcategory' => 'graphic_design',
                'phrases' => ['مصمم جرافيك', 'تصميم جرافيكي', 'جرافيك ديزاين'],
            ],
            [
                'category' => 'content_design_media',
                'subcategory' => 'photo_video',
                'phrases' => ['مصور فوتوغرافي', 'تصوير فوتوغرافي', 'مصور فيديو', 'تصوير فيديو', 'مونتاج فيديو'],
            ],
            [
                'category' => 'content_design_media',
                'subcategory' => 'social_pages_copywriting',
                'phrases' => ['اداره الصفحات', 'اداره صفحات', 'كاتب محتوى', 'كتابه المحتوى', 'سوشيال ميديا'],
            ],
            [
                'category' => 'content_design_media',
                'subcategory' => 'content_media',
                'phrases' => ['صانع محتوى', 'صناعه المحتوى', 'محرر صحفي', 'اعلامي'],
            ],
            [
                'category' => 'finance',
                'subcategory' => 'financial_analysis_budgeting',
                'phrases' => ['محلل مالي', 'تحليل مالي', 'اعداد الموازنات', 'الموازنات الماليه'],
            ],
            [
                'category' => 'finance',
                'subcategory' => 'audit',
                'phrases' => ['مراجع حسابات', 'مراجع مالي', 'مراجعه وتدقيق', 'التدقيق المالي'],
            ],
            [
                'category' => 'finance',
                'subcategory' => 'treasury',
                'phrases' => ['امين خزنه', 'امين صندوق', 'مسؤول خزينة', 'مسؤول خزينه'],
            ],
            [
                'category' => 'finance',
                'subcategory' => 'accounting',
                'phrases' => ['محاسب', 'محاسبه'],
            ],
            [
                'category' => 'administration_services',
                'subcategory' => 'human_resources',
                'phrases' => ['موارد بشريه', 'اخصائي موارد', 'مسؤول موارد'],
            ],
            [
                'category' => 'administration_services',
                'subcategory' => 'customer_service_inquiries',
                'phrases' => ['خدمه عملاء', 'موظف استعلامات', 'مركز اتصال', 'كول سنتر'],
            ],
            [
                'category' => 'administration_services',
                'subcategory' => 'administration_secretarial',
                'phrases' => ['سكرتير', 'سكرتاريه', 'مساعد اداري', 'موظف اداري', 'مدير مكتب'],
            ],
            [
                'category' => 'medical',
                'subcategory' => 'nursing',
                'phrases' => ['ممرض', 'ممرضه', 'تمريض'],
            ],
            [
                'category' => 'medical',
                'subcategory' => 'pharmacy_laboratories',
                'phrases' => ['صيدلي', 'صيدله', 'فني مختبر', 'مختبر طبي', 'تحاليل طبيه'],
            ],
            [
                'category' => 'medical',
                'subcategory' => 'hospitals_healthcare',
                'phrases' => ['طبيب', 'مستشفى', 'مرفق صحي', 'منشاه صحيه'],
            ],
            [
                'category' => 'hospitality_catering_travel',
                'subcategory' => 'restaurants',
                'phrases' => ['شيف', 'طباخ', 'نادل', 'عامل مطعم', 'مدير مطعم'],
            ],
            [
                'category' => 'hospitality_catering_travel',
                'subcategory' => 'catering_companies',
                'phrases' => ['شركه تموين', 'خدمات تموين', 'تعهدات تموينيه', 'خدمات الاعاشه'],
            ],
            [
                'category' => 'hospitality_catering_travel',
                'subcategory' => 'ticketing_aviation',
                'phrases' => ['حجز تذاكر', 'حجوزات طيران', 'وكاله سفر', 'موظف طيران'],
            ],
            [
                'category' => 'education',
                'subcategory' => 'kindergarten',
                'phrases' => ['رياض اطفال', 'معلم روضه', 'معلمه روضه'],
            ],
            [
                'category' => 'education',
                'subcategory' => 'universities_colleges',
                'phrases' => ['استاذ جامعي', 'محاضر جامعي', 'جامعه', 'كليه'],
            ],
            [
                'category' => 'education',
                'subcategory' => 'schools',
                'phrases' => ['مدرسه ابتدائيه', 'مدرسه ثانويه', 'اداره مدرسه'],
            ],
            [
                'category' => 'education',
                'subcategory' => 'teaching',
                'phrases' => ['معلم', 'معلمه', 'مدرس', 'مدرسه', 'تدريس'],
            ],
        ];
    }

    /** @param array<int, string> $phrases */
    private function containsAny(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($text, $this->normalize($phrase))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(
            ['أ', 'إ', 'آ', 'ى', 'ة', 'ؤ', 'ئ'],
            ['ا', 'ا', 'ا', 'ي', 'ه', 'و', 'ي'],
            $value,
        );

        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }
}
