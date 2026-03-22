<?php

namespace App\Services\AI;

class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cacheReadTokens,
        public readonly string $model,
        public readonly int $durationMs,
    ) {}
}
