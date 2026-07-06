<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class JobDescriptionGeneratorService
{
    public function __construct(private OpenAiClient $openAi) {}

    public function generate(array $data): array
    {
        if ($this->openAi->isConfigured()) {
            try {
                return $this->generateWithOpenAi($data);
            } catch (Throwable) {
                //
            }
        }

        return $this->fallback($data);
    }

    private function generateWithOpenAi(array $data): array
    {
        $text = $this->openAi->text(
            'أنت خبير موارد بشرية لمنصة مدارات. أنشئ وصفا وظيفيا عربيا احترافيا. أرجع JSON فقط بالمفاتيح: description كنص واحد، responsibilities كمصفوفة من 3 إلى 5 عناصر، suggested_skills كمصفوفة مهارات. لا تضف markdown.',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
        );

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response.');
        }

        return [
            'description' => (string) ($decoded['description'] ?? ''),
            'responsibilities' => array_values($decoded['responsibilities'] ?? []),
            'suggested_skills' => array_values($decoded['suggested_skills'] ?? []),
        ];
    }

    private function fallback(array $data): array
    {
        $title = $data['title'] ?? 'وظيفة جديدة';
        $location = $data['location'] ?? 'عن بعد';
        $level = $data['experience_level'] ?? 'متوسط';
        $skills = $data['required_skills'] ?? ['التواصل', 'حل المشكلات'];

        return [
            'description' => "نبحث عن {$title} للانضمام إلى فريقنا في {$location}. يناسب الدور مرشحا بمستوى خبرة {$level} ولديه قدرة على تنفيذ المهام بجودة عالية والعمل ضمن بيئة احترافية.",
            'responsibilities' => [
                'تنفيذ المهام اليومية المرتبطة بالدور وفق أهداف واضحة.',
                'التعاون مع الفرق الداخلية لتحسين جودة المخرجات.',
                'متابعة مؤشرات الأداء وتقديم اقتراحات تطوير عملية.',
            ],
            'suggested_skills' => array_values(array_unique(array_merge($skills, ['التواصل', 'إدارة الوقت']))),
        ];
    }
}
