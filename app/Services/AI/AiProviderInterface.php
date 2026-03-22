<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    public function generate(string $systemPrompt, string $userMessage, int $maxTokens): AiResponse;
    public function name(): string;
    public function supportsCaching(): bool;
}
