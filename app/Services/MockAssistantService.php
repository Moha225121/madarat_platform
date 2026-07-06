<?php

namespace App\Services;

class MockAssistantService
{
    public function respond(string $prompt, string $context = ''): string
    {
        $text = mb_strtolower($prompt);
        $hasContext = trim($context) !== '';
        $prefix = $hasContext ? 'اعتمادا على بيانات ملفك المتاحة في مدارات، ' : '';

        if (str_contains($text, 'سيرة') || str_contains($text, 'cv')) {
            return $prefix.'ابدأ بملخص مهني قصير، ثم أضف إنجازات رقمية لكل تجربة، واجعل المهارات مطابقة للوظيفة المستهدفة. راجع المهارات المستخرجة من سيرتك وحدد المهارات الناقصة قبل التقديم.';
        }

        if (str_contains($text, 'وظائف') || str_contains($text, 'مناسبة')) {
            return $prefix.'أفضل الوظائف لك هي التي تجمع بين مهاراتك، مجالك المهني، موقعك، ومستوى خبرتك. حدّث ملفك وسيرتك لتحصل على مطابقة أدق.';
        }

        if (str_contains($text, 'مقابلة')) {
            return $prefix.'راجع وصف الوظيفة، حضّر أمثلة باستخدام STAR، وتدرّب على شرح تجربة أو مشروع واحد بوضوح خلال دقيقتين.';
        }

        if (str_contains($text, 'وصف')) {
            return 'اكتب وصفا واضحا يبدأ بالهدف من الدور، ثم المسؤوليات، المهارات المطلوبة، ومؤشرات النجاح خلال أول 90 يوما.';
        }

        return $prefix.'أنا مساعد مدارات الذكي. أستطيع مساعدتك في تحسين السيرة، اختيار الوظائف المناسبة، الاستعداد للمقابلة، أو صياغة وصف وظيفي.';
    }
}
