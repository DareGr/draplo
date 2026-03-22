<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class AnthropicProvider implements AiProviderInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function generate(string $systemPrompt, string $userMessage, int $maxTokens): AiResponse
    {
        $startTime = microtime(true);

        $response = Http::timeout(120)
            ->connectTimeout(10)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta' => 'prompt-caching-2024-07-31',
                'content-type' => 'application/json',
            ])
            ->post(self::API_URL, [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => [
                    [
                        'type' => 'text',
                        'text' => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Anthropic API error ({$response->status()}): " . $response->body()
            );
        }

        $data = $response->json();
        $content = $data['content'][0]['text'] ?? '';
        $usage = $data['usage'] ?? [];

        return new AiResponse(
            content: $content,
            inputTokens: $usage['input_tokens'] ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
            cacheReadTokens: $usage['cache_read_input_tokens'] ?? 0,
            model: $this->model,
            durationMs: $durationMs,
        );
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function supportsCaching(): bool
    {
        return true;
    }
}
