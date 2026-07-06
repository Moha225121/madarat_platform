<?php

namespace App\Services;

use App\Models\JobSeekerProfile;
use Illuminate\Http\UploadedFile;

class MockCvAnalysisService
{
    public function analyze(UploadedFile $file, JobSeekerProfile $profile): array
    {
        $field = $profile->field ?: 'تقنية المعلومات';
        $skills = match ($field) {
            'التسويق' => ['التسويق الرقمي', 'كتابة المحتوى', 'تحليل الحملات', 'SEO'],
            'المحاسبة' => ['Excel', 'إعداد التقارير', 'تحليل التكاليف', 'الضرائب'],
            'التصميم' => ['Figma', 'تصميم واجهات', 'هوية بصرية', 'بحث المستخدم'],
            default => ['Laravel', 'React', 'TypeScript', 'SQL', 'تحليل المتطلبات'],
        };

        return [
            'score' => 82,
            'extracted_skills' => $skills,
            'missing_skills' => ['إدارة المنتجات', 'العرض التقديمي', 'اللغة الإنجليزية المهنية'],
            'education_summary' => 'درجة جامعية أو تدريب مهني مناسب للمجال مع أساس معرفي جيد.',
            'experience_summary' => 'خبرة عملية متوسطة تظهر قدرة على تنفيذ المهام والتعاون مع فرق متعددة.',
            'strengths' => ['وضوح المسار المهني', 'مهارات تقنية قابلة للمطابقة', 'خبرة عملية ذات صلة'],
            'recommendations' => ['أضف نتائج رقمية لكل تجربة.', 'اختصر الملخص المهني في 4 أسطر.', 'رتب المهارات حسب الوظيفة المستهدفة.'],
        ];
    }
}
