<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function text(string $instructions, string $input, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $response = Http::withToken(config('services.openai.key'))
            ->acceptJson()
            ->timeout(45)
            ->post(rtrim(config('services.openai.base_url'), '/').'/responses', [
                'model' => $options['model'] ?? config('services.openai.model'),
                'instructions' => $instructions,
                'input' => $input,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI request failed: '.$response->body());
        }

        return $this->extractText($response->json());
    }

    public function textWithFile(string $instructions, UploadedFile $file, string $input, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $mimeType = $file->getMimeType() ?: 'application/pdf';
        $fileData = 'data:'.$mimeType.';base64,'.base64_encode(file_get_contents($file->getRealPath()));

        $response = Http::withToken(config('services.openai.key'))
            ->acceptJson()
            ->timeout(90)
            ->post(rtrim(config('services.openai.base_url'), '/').'/responses', [
                'model' => $options['model'] ?? config('services.openai.model'),
                'instructions' => $instructions,
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_file',
                                'filename' => $file->getClientOriginalName(),
                                'file_data' => $fileData,
                            ],
                            [
                                'type' => 'input_text',
                                'text' => $input,
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI file request failed: '.$response->body());
        }

        return $this->extractText($response->json());
    }

    public function textWithStoredFile(string $instructions, string $path, string $filename, string $mimeType, string $input, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        if (! is_file($path)) {
            throw new RuntimeException('Stored file was not found.');
        }

        $fileData = 'data:'.$mimeType.';base64,'.base64_encode(file_get_contents($path));

        $response = Http::withToken(config('services.openai.key'))
            ->acceptJson()
            ->timeout(90)
            ->post(rtrim(config('services.openai.base_url'), '/').'/responses', [
                'model' => $options['model'] ?? config('services.openai.model'),
                'instructions' => $instructions,
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_file',
                                'filename' => $filename,
                                'file_data' => $fileData,
                            ],
                            [
                                'type' => 'input_text',
                                'text' => $input,
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI stored file request failed: '.$response->body());
        }

        return $this->extractText($response->json());
    }

    private function extractText(array $payload): string
    {
        if (isset($payload['output_text']) && is_string($payload['output_text'])) {
            return trim($payload['output_text']);
        }

        $parts = [];

        foreach ($payload['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }

        $text = trim(implode("\n", $parts));

        if ($text === '') {
            throw new RuntimeException('OpenAI response did not include text output.');
        }

        return $text;
    }
}
