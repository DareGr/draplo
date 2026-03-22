<?php

namespace App\Services\AI;

use App\Services\SettingsService;

class AiProviderFactory
{
    public function __construct(
        private SettingsService $settings
    ) {}

    public function resolve(): AiProviderInterface
    {
        $config = $this->settings->getAiConfig();
        $provider = $config['provider'];
        $model = $config['model'];

        return match ($provider) {
            'anthropic' => new AnthropicProvider(config('services.anthropic.api_key'), $model),
            'gemini' => new GeminiProvider(config('services.gemini.api_key'), $model),
            default => throw new \InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    }
}
