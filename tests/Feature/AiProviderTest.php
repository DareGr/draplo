<?php

use App\Services\AI\AiProviderFactory;
use App\Services\AI\AnthropicProvider;
use App\Services\AI\GeminiProvider;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
    config(['services.anthropic.api_key' => 'test-key']);
    config(['services.gemini.api_key' => 'test-key']);
});

it('factory resolves AnthropicProvider by default', function () {
    $factory = app(AiProviderFactory::class);
    $provider = $factory->resolve();

    expect($provider)->toBeInstanceOf(AnthropicProvider::class);
    expect($provider->name())->toBe('anthropic');
});

it('factory resolves GeminiProvider when settings updated', function () {
    $settings = app(SettingsService::class);
    $settings->set('ai_provider', 'gemini');

    // Need a fresh factory instance since SettingsService caches internally
    $factory = app(AiProviderFactory::class);
    $provider = $factory->resolve();

    expect($provider)->toBeInstanceOf(GeminiProvider::class);
    expect($provider->name())->toBe('gemini');
});

it('factory throws InvalidArgumentException for unknown provider', function () {
    $settings = app(SettingsService::class);
    $settings->set('ai_provider', 'openai');

    $factory = app(AiProviderFactory::class);
    $factory->resolve();
})->throws(InvalidArgumentException::class, 'Unknown AI provider: openai');

it('AnthropicProvider sends correct headers', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Hello']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50, 'cache_read_input_tokens' => 0],
        ]),
    ]);

    $provider = new AnthropicProvider('test-api-key', 'claude-sonnet-4-6');
    $response = $provider->generate('System prompt', 'User message', 4000);

    expect($response->content)->toBe('Hello');
    expect($response->inputTokens)->toBe(100);
    expect($response->outputTokens)->toBe(50);
    expect($response->model)->toBe('claude-sonnet-4-6');

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-api-key', 'test-api-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request->url() === 'https://api.anthropic.com/v1/messages';
    });
});

it('GeminiProvider sends correct request format', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Hello from Gemini']]]]],
            'usageMetadata' => ['promptTokenCount' => 80, 'candidatesTokenCount' => 150, 'cachedContentTokenCount' => 0],
        ]),
    ]);

    $provider = new GeminiProvider('test-gemini-key', 'gemini-2.5-pro');
    $response = $provider->generate('System prompt', 'User message', 4000);

    expect($response->content)->toBe('Hello from Gemini');
    expect($response->inputTokens)->toBe(80);
    expect($response->outputTokens)->toBe(150);
    expect($response->model)->toBe('gemini-2.5-pro');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($request->url(), 'gemini-2.5-pro:generateContent')
            && $request['systemInstruction']['parts'][0]['text'] === 'System prompt'
            && $request['contents'][0]['parts'][0]['text'] === 'User message';
    });
});
