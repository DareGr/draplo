<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function generate(string $systemPrompt, string $userMessage, int $maxTokens): AiResponse
    {
        $startTime = microtime(true);

        $url = self::API_BASE . "/{$this->model}:generateContent";

        $response = Http::timeout(120)
            ->connectTimeout(10)
            ->withQueryParameters(['key' => $this->apiKey])
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $userMessage]],
                    ],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => $maxTokens,
                ],
            ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Gemini API error ({$response->status()}): " . $response->body()
            );
        }

        $data = $response->json();
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $usage = $data['usageMetadata'] ?? [];

        return new AiResponse(
            content: $content,
            inputTokens: $usage['promptTokenCount'] ?? 0,
            outputTokens: $usage['candidatesTokenCount'] ?? 0,
            cacheReadTokens: $usage['cachedContentTokenCount'] ?? 0,
            model: $this->model,
            durationMs: $durationMs,
        );
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function supportsCaching(): bool
    {
        return true;
    }
}
