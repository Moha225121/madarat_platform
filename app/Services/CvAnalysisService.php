<?php

namespace App\Services;

use App\Models\JobSeekerProfile;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

class CvAnalysisService
{
    public function __construct(private OpenAiClient $openAi) {}

    public function analyze(UploadedFile $file, JobSeekerProfile $profile): array
    {
        if (! $this->openAi->isConfigured()) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        return $this->normalize($this->analyzeWithOpenAi($file, $profile));
    }

    private function analyzeWithOpenAi(UploadedFile $file, JobSeekerProfile $profile): array
    {
        $instructions = 'أنت محلل سير ذاتية لمنصة مدارات. حلل السيرة بموضوعية وأرجع JSON فقط بدون markdown. يجب أن تكون المفاتيح: score رقم من 0 إلى 100، extracted_skills مصفوفة، missing_skills مصفوفة، education_summary نص، experience_summary نص، strengths مصفوفة، recommendations مصفوفة. اجعل اللغة عربية مهنية ومختصرة.';

        $context = [
            'target_field' => $profile->field,
            'headline' => $profile->headline,
            'city' => $profile->city,
            'bio' => $profile->bio,
        ];

        $prompt = "حلل هذه السيرة الذاتية اعتمادا على الملف المرفق وسياق الباحث التالي:\n".
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (strtolower($file->getClientOriginalExtension()) === 'pdf') {
            $text = $this->openAi->textWithFile($instructions, $file, $prompt);
        } else {
            $text = $this->openAi->text($instructions, $prompt."\n\nنص السيرة الذاتية:\n".$this->extractText($file));
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid CV analysis JSON response.');
        }

        return $decoded;
    }

    private function extractText(UploadedFile $file): string
    {
        if (strtolower($file->getClientOriginalExtension()) === 'docx') {
            return $this->extractDocxText($file);
        }

        $text = trim((string) file_get_contents($file->getRealPath()));

        if ($text === '') {
            throw new RuntimeException('Unable to extract text from CV file.');
        }

        return mb_substr($text, 0, 20000);
    }

    private function extractDocxText(UploadedFile $file): string
    {
        $zip = new ZipArchive();

        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('Unable to open DOCX file.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            throw new RuntimeException('Unable to read DOCX document text.');
        }

        $xml = preg_replace('/<\/w:p>/', "\n", $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            throw new RuntimeException('DOCX text is empty.');
        }

        return mb_substr($text, 0, 20000);
    }

    private function normalize(array $result): array
    {
        return [
            'score' => max(0, min(100, (int) ($result['score'] ?? 0))),
            'extracted_skills' => array_values($result['extracted_skills'] ?? []),
            'missing_skills' => array_values($result['missing_skills'] ?? []),
            'education_summary' => (string) ($result['education_summary'] ?? ''),
            'experience_summary' => (string) ($result['experience_summary'] ?? ''),
            'strengths' => array_values($result['strengths'] ?? []),
            'recommendations' => array_values($result['recommendations'] ?? []),
        ];
    }
}
