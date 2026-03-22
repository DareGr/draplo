# Phase 2A — AI Generation Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a multi-provider AI generation engine (Anthropic + Gemini) that takes wizard data and produces a complete Laravel project scaffold, with admin-configurable provider selection, caching, rate limiting, and cost tracking.

**Architecture:** Provider interface pattern with AnthropicProvider and GeminiProvider implementations. AiProviderFactory resolves active provider from DB settings → .env fallback. GenerationService orchestrates 3-layer prompt building, caching, API call, XML output parsing, validation, and storage. GenerateProjectJob runs async via Redis queue.

**Tech Stack:** Laravel 12, PHP 8.3, Anthropic Messages API, Google Gemini API, Redis (queue), Pest PHP

**Spec:** `docs/superpowers/specs/2026-03-22-phase2a-ai-engine-design.md`

---

## File Structure

### New files

```
app/Services/AI/
├── AiProviderInterface.php       — contract: generate(), name(), supportsCaching()
├── AiResponse.php                — value object: content, tokens, cost data
├── AiProviderFactory.php         — resolves active provider from settings
├── AnthropicProvider.php         — Anthropic Messages API with prompt caching
└── GeminiProvider.php            — Google Gemini generateContent API

app/Services/
├── GenerationService.php         — orchestrates: build prompt → cache check → API → parse → validate → store
├── OutputParserService.php       — extracts files from XML tags
└── SettingsService.php           — DB settings with .env fallback

app/Http/Controllers/
├── GenerationController.php      — generate, status, regenerate, preview endpoints
└── Admin/AdminController.php     — settings CRUD, platform stats

app/Http/Middleware/
├── EnsureAdmin.php               — checks is_admin on user
└── RateLimitGeneration.php       — 5/hr per user (completed + in-flight)

app/Jobs/
└── GenerateProjectJob.php        — queued generation with timeout and error handling

app/Prompts/
├── system-prompt.md              — master system prompt (~3000 tokens)
└── model-suggestion.md           — model suggestion prompt (~500 tokens)

database/migrations/
├── xxxx_add_generation_columns.php  — add cache_read_tokens, provider to generations
├── xxxx_create_settings_table.php   — key-value settings store
└── xxxx_add_is_admin_to_users.php   — is_admin boolean on users

database/seeders/
└── SettingsSeeder.php            — seed default AI settings

tests/fixtures/
└── sample-generation-output.xml  — sample 7-file XML output for parser tests

tests/Feature/
├── GenerationTest.php
├── AdminTest.php
├── SuggestModelsTest.php
├── OutputParserServiceTest.php    — in Feature/ because Pest setup only extends TestCase there
├── GenerationServiceTest.php
└── AiProviderTest.php
```

### Files to modify

```
config/services.php               — add ai, anthropic, gemini config sections
.env.example                      — add AI_PROVIDER, AI_MODEL, API keys
routes/api.php                    — add generation, admin, suggest routes
app/Models/User.php               — add is_admin to fillable + casts
app/Models/Generation.php         — add cache_read_tokens, provider to fillable
database/seeders/DatabaseSeeder.php — add SettingsSeeder
database/seeders/DevUserSeeder.php  — set is_admin: true
bootstrap/app.php                 — register EnsureAdmin middleware alias
```

---

## Task 1: Database Migrations + Settings Model

**Files:**
- Create: `database/migrations/xxxx_add_generation_columns.php`
- Create: `database/migrations/xxxx_create_settings_table.php`
- Create: `database/migrations/xxxx_add_is_admin_to_users.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Generation.php`

- [ ] **Step 1: Create migration to add columns to generations table**

```bash
php artisan make:migration add_provider_and_cache_to_generations_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->integer('cache_read_tokens')->default(0)->after('completion_tokens');
            $table->string('provider', 50)->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropColumn(['cache_read_tokens', 'provider']);
        });
    }
};
```

- [ ] **Step 2: Create settings table migration**

```bash
php artisan make:migration create_settings_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

- [ ] **Step 3: Create is_admin migration**

```bash
php artisan make:migration add_is_admin_to_users_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('generation_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
```

- [ ] **Step 4: Update User model** — add `is_admin` to `$fillable` and `$casts`:

In `app/Models/User.php`, add `'is_admin'` to `$fillable` array and add `'is_admin' => 'boolean'` to `casts()`.

- [ ] **Step 5: Update Generation model** — add `cache_read_tokens` and `provider` to `$fillable`:

In `app/Models/Generation.php`, add `'cache_read_tokens'` and `'provider'` to `$fillable` array.

- [ ] **Step 6: Update DevUserSeeder** — set `is_admin: true`:

In `database/seeders/DevUserSeeder.php`, add `'is_admin' => true` to the `updateOrCreate` data array.

- [ ] **Step 7: Update UserFactory** — add `is_admin` default:

In `database/factories/UserFactory.php`, add `'is_admin' => false` to the `definition()` return array.

- [ ] **Step 8: Run migrations**

```bash
docker-compose up -d
php artisan migrate
```

Expected: 3 new migrations run successfully.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add settings table, is_admin column, generation tracking columns"
```

---

## Task 2: SettingsService + Config

**Files:**
- Create: `app/Services/SettingsService.php`
- Create: `database/seeders/SettingsSeeder.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create SettingsService**

Create `app/Services/SettingsService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SettingsService
{
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $row = DB::table('settings')->where('key', $key)->first();

        $value = $row ? $row->value : $default;
        $this->cache[$key] = $value;

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $exists = DB::table('settings')->where('key', $key)->exists();

        if ($exists) {
            DB::table('settings')->where('key', $key)->update([
                'value' => (string) $value,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => (string) $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->cache[$key] = (string) $value;
    }

    public function all(): array
    {
        return DB::table('settings')->pluck('value', 'key')->toArray();
    }

    public function getAiConfig(): array
    {
        return [
            'provider' => $this->get('ai_provider', config('services.ai.provider', 'anthropic')),
            'model' => $this->get('ai_model', config('services.ai.model', 'claude-sonnet-4-6')),
            'max_tokens' => (int) $this->get('ai_max_tokens', config('services.ai.max_tokens', 16000)),
        ];
    }
}
```

- [ ] **Step 2: Add AI config to config/services.php**

Append to the return array in `config/services.php`:

```php
'ai' => [
    'provider' => env('AI_PROVIDER', 'anthropic'),
    'model' => env('AI_MODEL', 'claude-sonnet-4-6'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 16000),
],

'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
],

'gemini' => [
    'api_key' => env('GEMINI_API_KEY'),
],
```

- [ ] **Step 3: Update .env.example**

Add after the existing feature flags:

```env
# AI Provider Configuration
AI_PROVIDER=anthropic
AI_MODEL=claude-sonnet-4-6
AI_MAX_TOKENS=16000
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
```

- [ ] **Step 4: Create SettingsSeeder**

Create `database/seeders/SettingsSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'ai_provider' => config('services.ai.provider', 'anthropic'),
            'ai_model' => config('services.ai.model', 'claude-sonnet-4-6'),
            'ai_max_tokens' => (string) config('services.ai.max_tokens', 16000),
            'generation_rate_limit' => '5',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
```

- [ ] **Step 5: Register SettingsService as singleton**

In `app/Providers/AppServiceProvider.php`, add to the `register()` method:

```php
$this->app->singleton(\App\Services\SettingsService::class);
```

This ensures the in-memory cache works correctly — all injections share the same instance per request.

- [ ] **Step 6: Add SettingsSeeder to DatabaseSeeder**

Add `SettingsSeeder::class` to the `$this->call([...])` array.

- [ ] **Step 6: Run seeder and verify**

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="echo app(\App\Services\SettingsService::class)->get('ai_provider');"
# Expected: anthropic
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add SettingsService with DB → .env fallback, AI config"
```

---

## Task 3: AI Provider Interface + AiResponse + Factory

**Files:**
- Create: `app/Services/AI/AiProviderInterface.php`
- Create: `app/Services/AI/AiResponse.php`
- Create: `app/Services/AI/AiProviderFactory.php`

- [ ] **Step 1: Create AiProviderInterface**

Create `app/Services/AI/AiProviderInterface.php`:

```php
<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    public function generate(string $systemPrompt, string $userMessage, int $maxTokens): AiResponse;

    public function name(): string;

    public function supportsCaching(): bool;
}
```

- [ ] **Step 2: Create AiResponse**

Create `app/Services/AI/AiResponse.php`:

```php
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
```

- [ ] **Step 3: Create AiProviderFactory**

Create `app/Services/AI/AiProviderFactory.php`:

```php
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
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add AiProviderInterface, AiResponse value object, AiProviderFactory"
```

---

## Task 4: AnthropicProvider

**Files:**
- Create: `app/Services/AI/AnthropicProvider.php`

- [ ] **Step 1: Create AnthropicProvider**

Create `app/Services/AI/AnthropicProvider.php`:

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: add AnthropicProvider with prompt caching support"
```

---

## Task 5: GeminiProvider

**Files:**
- Create: `app/Services/AI/GeminiProvider.php`

- [ ] **Step 1: Create GeminiProvider**

Create `app/Services/AI/GeminiProvider.php`:

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: add GeminiProvider for Google Gemini API"
```

---

## Task 6: OutputParserService + Test Fixtures

**Files:**
- Create: `app/Services/OutputParserService.php`
- Create: `tests/fixtures/sample-generation-output.xml`
- Create: `tests/Unit/OutputParserServiceTest.php`

- [ ] **Step 1: Create sample fixture**

Create `tests/fixtures/sample-generation-output.xml` — a realistic sample AI output with 7+ files in XML format. Include CLAUDE.md, PROJECT.md, todo.md, architecture.md, constants.md, patterns.md, decisions.md, and at least one migration file. Each file should have realistic content (~50-100 lines). Include PHP code with angle brackets to test edge cases.

- [ ] **Step 2: Create OutputParserService**

Create `app/Services/OutputParserService.php`:

```php
<?php

namespace App\Services;

class OutputParserService
{
    private const REQUIRED_FILES = [
        'CLAUDE.md',
        'PROJECT.md',
        'todo.md',
        '.claude-reference/architecture.md',
        '.claude-reference/constants.md',
        '.claude-reference/patterns.md',
        '.claude-reference/decisions.md',
    ];

    public function parse(string $content): array
    {
        $files = [];
        $pattern = '/<file\s+path="([^"]+)">([\s\S]*?)<\/file>/';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $files[] = [
                'path' => trim($match[1]),
                'content' => trim($match[2]),
            ];
        }

        return $files;
    }

    public function validate(array $files): array
    {
        $errors = [];
        $paths = array_column($files, 'path');

        // Check required files
        foreach (self::REQUIRED_FILES as $required) {
            if (!in_array($required, $paths)) {
                $errors[] = "Missing required file: {$required}";
            }
        }

        // Check migration files contain Schema::create
        foreach ($files as $file) {
            if (str_starts_with($file['path'], 'database/migrations/') && str_ends_with($file['path'], '.php')) {
                if (!str_contains($file['content'], 'Schema::create') && !str_contains($file['content'], 'Schema::table')) {
                    $errors[] = "Migration {$file['path']} does not contain Schema::create or Schema::table";
                }
            }

            // Check routes file
            if ($file['path'] === 'routes/api.php' && !str_contains($file['content'], 'Route::')) {
                $errors[] = "routes/api.php does not contain Route::";
            }

            // Check file size (50KB max)
            if (strlen($file['content']) > 50 * 1024) {
                $errors[] = "File {$file['path']} exceeds 50KB limit";
            }
        }

        return $errors;
    }
}
```

- [ ] **Step 3: Write OutputParserServiceTest**

Create `tests/Unit/OutputParserServiceTest.php` (as a Feature test since it needs the app):

Actually, this is pure PHP — no DB needed. But put it in Feature for consistency with the Pest setup.

Create `tests/Feature/OutputParserServiceTest.php`:

```php
<?php

use App\Services\OutputParserService;

beforeEach(function () {
    $this->parser = new OutputParserService();
});

it('parses valid XML output into file array', function () {
    $content = '<file path="CLAUDE.md"># Project Context</file><file path="PROJECT.md">## Overview</file>';
    $files = $this->parser->parse($content);

    expect($files)->toHaveCount(2);
    expect($files[0]['path'])->toBe('CLAUDE.md');
    expect($files[0]['content'])->toBe('# Project Context');
    expect($files[1]['path'])->toBe('PROJECT.md');
});

it('handles PHP code with angle brackets in content', function () {
    $content = '<file path="database/migrations/create_users.php"><?php
use Illuminate\Database\Schema\Blueprint;
Schema::create(\'users\', function (Blueprint $table) {
    $table->id();
    if ($table->hasColumn(\'name\')) { /* noop */ }
});
</file>';

    $files = $this->parser->parse($content);

    expect($files)->toHaveCount(1);
    expect($files[0]['content'])->toContain('Schema::create');
    expect($files[0]['content'])->toContain('$table->id()');
});

it('returns empty array for malformed input', function () {
    expect($this->parser->parse('no xml tags here'))->toBe([]);
    expect($this->parser->parse(''))->toBe([]);
});

it('trims whitespace from content', function () {
    $content = '<file path="test.md">
    content with leading whitespace
    </file>';

    $files = $this->parser->parse($content);
    expect($files[0]['content'])->toBe('content with leading whitespace');
});

it('validates required files are present', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
    ];

    $errors = $this->parser->validate($files);
    expect($errors)->not->toBeEmpty();
    expect(collect($errors)->filter(fn($e) => str_contains($e, 'todo.md')))->not->toBeEmpty();
});

it('validates migration files contain Schema::create', function () {
    $files = [
        ['path' => 'database/migrations/2026_create_users.php', 'content' => '<?php echo "bad";'],
    ];

    $errors = $this->parser->validate($files);
    expect(collect($errors)->filter(fn($e) => str_contains($e, 'Schema::create')))->not->toBeEmpty();
});

it('passes validation with all required files', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
        ['path' => 'todo.md', 'content' => '- [ ] Task 1'],
        ['path' => '.claude-reference/architecture.md', 'content' => '# Arch'],
        ['path' => '.claude-reference/constants.md', 'content' => '# Const'],
        ['path' => '.claude-reference/patterns.md', 'content' => '# Patterns'],
        ['path' => '.claude-reference/decisions.md', 'content' => '# Decisions'],
    ];

    $errors = $this->parser->validate($files);
    expect($errors)->toBe([]);
});

it('rejects files over 50KB', function () {
    $files = [
        ['path' => 'huge.md', 'content' => str_repeat('x', 51 * 1024)],
    ];

    $errors = $this->parser->validate($files);
    expect(collect($errors)->filter(fn($e) => str_contains($e, '50KB')))->not->toBeEmpty();
});

it('parses the full sample fixture', function () {
    $fixturePath = base_path('tests/fixtures/sample-generation-output.xml');
    if (!file_exists($fixturePath)) {
        $this->markTestSkipped('Fixture file not found');
    }

    $content = file_get_contents($fixturePath);
    $files = $this->parser->parse($content);

    expect($files)->not->toBeEmpty();
    expect(count($files))->toBeGreaterThanOrEqual(7);
});
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Feature/OutputParserServiceTest.php
```

Expected: All pass.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add OutputParserService with XML parsing and validation"
```

---

## Task 7: System Prompt + Model Suggestion Prompt

**Files:**
- Create: `app/Prompts/system-prompt.md`
- Create: `app/Prompts/model-suggestion.md`

- [ ] **Step 1: Create system-prompt.md**

Create `app/Prompts/system-prompt.md` — the master system prompt (~3000 tokens). This is the most important file in the entire generation engine. It must instruct the AI to:

1. Act as a senior Laravel 12 architect
2. Output each file wrapped in `<file path="...">content</file>` XML tags
3. Generate all 7 required files + optional migrations and routes
4. Follow quality rules (valid PHP, correct naming, dependency-ordered migrations)
5. Handle multi-tenancy, integrations, and guest access when specified
6. Produce agent-ready documentation (for Claude Code / Cursor)

The prompt should be detailed and specific. Reference the spec Section 4 for all required content.

- [ ] **Step 2: Create model-suggestion.md**

Create `app/Prompts/model-suggestion.md`:

```markdown
You are a Laravel architect. Given an app description, suggest 5-8 Eloquent models that would form the core data layer.

For each model, provide:
- name: PascalCase model name
- description: One sentence explaining the model's purpose
- fields: Array of field objects with name (snake_case) and type

Valid field types: string, text, integer, decimal, boolean, timestamp, foreignId, json

Use foreignId for relationships (e.g., user_id, tenant_id). Use snake_case for field names.

Respond with ONLY a JSON array, no markdown, no explanation:
[{"name": "ModelName", "description": "...", "fields": [{"name": "field_name", "type": "string"}]}]
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add system prompt and model suggestion prompt"
```

---

## Task 8: GenerationService

**Files:**
- Create: `app/Services/GenerationService.php`

- [ ] **Step 1: Create GenerationService**

Create `app/Services/GenerationService.php`:

```php
<?php

namespace App\Services;

use App\Models\Generation;
use App\Models\Project;
use App\Services\AI\AiProviderFactory;
use App\Enums\ProjectStatusEnum;

class GenerationService
{
    public function __construct(
        private AiProviderFactory $providerFactory,
        private OutputParserService $parser,
        private SettingsService $settings,
    ) {}

    public function generate(Project $project): void
    {
        // Step 1: Check cache
        if ($this->tryCache($project)) {
            return;
        }

        // Step 2: Build prompts
        $systemPrompt = $this->buildSystemPrompt($project);
        $userMessage = $this->buildUserMessage($project);

        // Step 3: Call AI
        $maxTokens = (int) $this->settings->get('ai_max_tokens', config('services.ai.max_tokens', 16000));
        $provider = $this->providerFactory->resolve();
        $response = $provider->generate($systemPrompt, $userMessage, $maxTokens);

        // Step 4: Parse
        $files = $this->parser->parse($response->content);

        // Step 5: Validate (with retry)
        $errors = $this->parser->validate($files);
        if (!empty($errors)) {
            $retryMessage = $userMessage . "\n\nIMPORTANT: Your previous output was invalid. Issues: " . implode('; ', $errors) . ". Please regenerate ALL required files.";
            $response = $provider->generate($systemPrompt, $retryMessage, $maxTokens);
            $files = $this->parser->parse($response->content);
            $errors = $this->parser->validate($files);

            if (!empty($errors)) {
                throw new \RuntimeException('Generation validation failed after retry: ' . implode('; ', $errors));
            }
        }

        // Step 6: Store
        $inputHash = hash('sha256', json_encode($project->wizard_data));
        $project->update([
            'generation_output' => $files,
            'input_hash' => $inputHash,
            'status' => ProjectStatusEnum::Generated,
        ]);

        // Step 7: Track cost
        $cost = $this->calculateCost($provider->name(), $response);

        Generation::create([
            'project_id' => $project->id,
            'input_hash' => $inputHash,
            'prompt_tokens' => $response->inputTokens,
            'completion_tokens' => $response->outputTokens,
            'cache_read_tokens' => $response->cacheReadTokens,
            'cost_usd' => $cost,
            'model' => $response->model,
            'provider' => $provider->name(),
            'duration_ms' => $response->durationMs,
            'cached' => false,
            'created_at' => now(),
        ]);
    }

    private function tryCache(Project $project): bool
    {
        $inputHash = hash('sha256', json_encode($project->wizard_data));

        $cached = Project::where('user_id', $project->user_id)
            ->where('input_hash', $inputHash)
            ->whereNotNull('generation_output')
            ->where('id', '!=', $project->id)
            ->first();

        if (!$cached) {
            return false;
        }

        $project->update([
            'generation_output' => $cached->generation_output,
            'input_hash' => $inputHash,
            'status' => ProjectStatusEnum::Generated,
        ]);

        Generation::create([
            'project_id' => $project->id,
            'input_hash' => $inputHash,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cache_read_tokens' => 0,
            'cost_usd' => 0,
            'model' => 'cache',
            'provider' => 'cache',
            'duration_ms' => 0,
            'cached' => true,
            'created_at' => now(),
        ]);

        return true;
    }

    private function buildSystemPrompt(Project $project): string
    {
        // Layer 1: Base system prompt
        $base = file_get_contents(app_path('Prompts/system-prompt.md'));

        // Layer 2: Template-specific context
        $templateContext = '';
        if ($project->template_slug) {
            $contextPath = storage_path("app/templates/{$project->template_slug}/prompt-context.md");
            if (file_exists($contextPath)) {
                $templateContext = file_get_contents($contextPath);
            }
        }

        return $base . ($templateContext ? "\n\n" . $templateContext : '');
    }

    public function buildUserMessage(Project $project): string
    {
        $data = $project->wizard_data ?? [];
        $describe = $data['step_describe'] ?? [];
        $users = $data['step_users'] ?? [];
        $models = $data['step_models'] ?? [];
        $auth = $data['step_auth'] ?? [];
        $integrations = $data['step_integrations'] ?? [];

        $lines = [];

        // Project info
        $lines[] = "## Project";
        $lines[] = "Name: " . ($describe['name'] ?? 'Untitled');
        $lines[] = "Description: " . ($describe['description'] ?? '');
        $lines[] = "Problem it solves: " . ($describe['problem'] ?? '');
        $lines[] = '';

        // App type
        $lines[] = "## App Type";
        $lines[] = $users['app_type'] ?? 'not specified';
        $lines[] = '';

        // Roles
        $lines[] = "## User Roles";
        foreach ($users['roles'] ?? [] as $role) {
            $lines[] = "- {$role['name']}: {$role['description']}";
        }
        $lines[] = '';

        // Models
        $lines[] = "## Core Models";
        foreach ($models['models'] ?? [] as $model) {
            $locked = ($model['locked'] ?? false) ? ' (locked - required)' : '';
            $lines[] = "### {$model['name']}{$locked}";
            $lines[] = $model['description'] ?? '';
            $lines[] = "Fields:";
            foreach ($model['fields'] ?? [] as $field) {
                $lines[] = "- {$field['name']} ({$field['type']})";
            }
            $lines[] = '';
        }

        // Auth
        $lines[] = "## Authentication & Tenancy";
        $lines[] = "- Multi-tenant: " . (($auth['multi_tenant'] ?? false) ? 'Yes' : 'No');
        $lines[] = "- Auth method: " . ($auth['auth_method'] ?? 'sanctum');
        $guestAccess = $auth['guest_access'] ?? false;
        $lines[] = "- Guest access: " . ($guestAccess ? "Yes - " . ($auth['guest_description'] ?? '') : 'No');
        $lines[] = '';

        // Integrations
        $lines[] = "## Integrations";
        $lines[] = "Selected: " . implode(', ', $integrations['selected'] ?? []);
        if (!empty($integrations['notes'])) {
            $lines[] = "Notes: " . $integrations['notes'];
        }

        return implode("\n", $lines);
    }

    private function calculateCost(string $provider, \App\Services\AI\AiResponse $response): float
    {
        $input = $response->inputTokens;
        $output = $response->outputTokens;
        $cacheRead = $response->cacheReadTokens;
        $nonCached = $input - $cacheRead;

        return match ($provider) {
            'anthropic' => ($nonCached / 1_000_000 * 3) + ($cacheRead / 1_000_000 * 0.30) + ($output / 1_000_000 * 15),
            'gemini' => ($nonCached / 1_000_000 * 1.25) + ($cacheRead / 1_000_000 * 0.125) + ($output / 1_000_000 * 10),
            default => ($input / 1_000_000 * 3) + ($output / 1_000_000 * 15),
        };
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: add GenerationService with prompt building, caching, validation, cost tracking"
```

---

## Task 9: GenerateProjectJob

**Files:**
- Create: `app/Jobs/GenerateProjectJob.php`

- [ ] **Step 1: Create GenerateProjectJob**

Create `app/Jobs/GenerateProjectJob.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Services\GenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(
        public Project $project
    ) {}

    public function handle(GenerationService $generationService): void
    {
        $this->project->update(['status' => ProjectStatusEnum::Generating]);

        try {
            $generationService->generate($this->project);
        } catch (\Throwable $e) {
            $this->project->update(['status' => ProjectStatusEnum::Failed]);
            Log::error('Generation failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: add GenerateProjectJob with timeout and error handling"
```

---

## Task 10: Admin Middleware + AdminController

**Files:**
- Create: `app/Http/Middleware/EnsureAdmin.php`
- Create: `app/Http/Controllers/Admin/AdminController.php`
- Modify: `bootstrap/app.php` — register middleware alias

- [ ] **Step 1: Create EnsureAdmin middleware**

Create `app/Http/Middleware/EnsureAdmin.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->is_admin) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Register middleware alias**

In `bootstrap/app.php`, add the middleware alias inside the `withMiddleware` callback:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureAdmin::class,
    ]);
})
```

- [ ] **Step 3: Create AdminController**

Create `app/Http/Controllers/Admin/AdminController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private SettingsService $settings
    ) {}

    public function settings(): JsonResponse
    {
        return response()->json([
            'ai_provider' => $this->settings->get('ai_provider', config('services.ai.provider')),
            'ai_model' => $this->settings->get('ai_model', config('services.ai.model')),
            'ai_max_tokens' => (int) $this->settings->get('ai_max_tokens', config('services.ai.max_tokens')),
            'generation_rate_limit' => (int) $this->settings->get('generation_rate_limit', 5),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_provider' => ['sometimes', 'string', 'in:anthropic,gemini'],
            'ai_model' => ['sometimes', 'string', 'max:100'],
            'ai_max_tokens' => ['sometimes', 'integer', 'min:1000', 'max:100000'],
            'generation_rate_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        foreach ($validated as $key => $value) {
            $this->settings->set($key, $value);
        }

        return $this->settings();
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'users_count' => User::count(),
            'projects_count' => Project::count(),
            'generations_count' => Generation::count(),
            'total_cost_usd' => (float) Generation::sum('cost_usd'),
            'generations_today' => Generation::whereDate('created_at', today())->count(),
            'active_provider' => $this->settings->get('ai_provider', config('services.ai.provider')),
            'active_model' => $this->settings->get('ai_model', config('services.ai.model')),
        ]);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add EnsureAdmin middleware and AdminController (settings, stats)"
```

---

## Task 11: RateLimitGeneration Middleware + GenerationController

**Files:**
- Create: `app/Http/Middleware/RateLimitGeneration.php`
- Create: `app/Http/Controllers/GenerationController.php`

- [ ] **Step 1: Create RateLimitGeneration middleware**

Create `app/Http/Middleware/RateLimitGeneration.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Enums\ProjectStatusEnum;
use App\Models\Generation;
use App\Models\Project;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitGeneration
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()->id;
        $limit = (int) app(SettingsService::class)->get('generation_rate_limit', 5);

        // Count completed generations in last hour (all, including cached)
        $completedCount = Generation::whereHas('project', fn($q) => $q->where('user_id', $userId))
            ->where('created_at', '>', now()->subHour())
            ->count();

        // Count in-flight jobs
        $inFlightCount = Project::where('user_id', $userId)
            ->where('status', ProjectStatusEnum::Generating)
            ->count();

        if (($completedCount + $inFlightCount) >= $limit) {
            return response()->json([
                'message' => "Rate limit exceeded. Maximum {$limit} generations per hour.",
            ], 429)->withHeaders(['Retry-After' => '3600']);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Create GenerationController**

Create `app/Http/Controllers/GenerationController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatusEnum;
use App\Jobs\GenerateProjectJob;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class GenerationController extends Controller
{
    public function generate(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status !== ProjectStatusEnum::WizardDone) {
            return response()->json(['message' => 'Project must have completed wizard before generating. Use regenerate for already-generated projects.'], 422);
        }

        GenerateProjectJob::dispatch($project);

        return response()->json([
            'status' => 'generating',
            'message' => 'Generation started.',
        ], 202);
    }

    public function status(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $data = ['status' => $project->status->value];

        if ($project->status === ProjectStatusEnum::Generated) {
            $generation = $project->generations()->latest('created_at')->first();
            $files = collect($project->generation_output ?? [])
                ->map(fn($f) => ['path' => $f['path'], 'size' => strlen($f['content'] ?? '')])
                ->toArray();

            $data['files'] = $files;
            if ($generation) {
                $data['generation'] = [
                    'prompt_tokens' => $generation->prompt_tokens,
                    'completion_tokens' => $generation->completion_tokens,
                    'cost_usd' => $generation->cost_usd,
                    'model' => $generation->model,
                    'provider' => $generation->provider,
                    'duration_ms' => $generation->duration_ms,
                    'cached' => $generation->cached,
                ];
            }
        }

        return response()->json($data);
    }

    public function regenerate(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $project->update([
            'input_hash' => null,
            'generation_output' => null,
        ]);

        GenerateProjectJob::dispatch($project);

        return response()->json([
            'status' => 'generating',
            'message' => 'Regeneration started.',
        ], 202);
    }

    public function preview(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status !== ProjectStatusEnum::Generated) {
            return response()->json(['message' => 'No generated output available.'], 404);
        }

        return response()->json(['files' => $project->generation_output ?? []]);
    }

    public function previewFile(Project $project, string $filepath): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status !== ProjectStatusEnum::Generated) {
            return response()->json(['message' => 'No generated output available.'], 404);
        }

        $file = collect($project->generation_output ?? [])
            ->firstWhere('path', $filepath);

        if (!$file) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return response()->json($file);
    }
}
```

- [ ] **Step 3: Register RateLimitGeneration middleware alias**

In `bootstrap/app.php`, add to the aliases array:

```php
'rate_limit_generation' => \App\Http\Middleware\RateLimitGeneration::class,
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add GenerationController with rate limiting middleware"
```

---

## Task 12: Suggest Models Endpoint + Routes

**Files:**
- Modify: `app/Http/Controllers/Wizard/WizardController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Add suggest method to WizardController**

Add to `app/Http/Controllers/Wizard/WizardController.php`:

```php
use App\Services\AI\AiProviderFactory;
use Illuminate\Http\Request;

// Update the existing constructor to add AiProviderFactory:
// Change from: public function __construct(private TemplateService $templateService) {}
// To:
public function __construct(
    private TemplateService $templateService,
    private AiProviderFactory $providerFactory,
) {}

public function suggest(Request $request, Project $project): JsonResponse
{
    if ($project->user_id !== auth()->id()) {
        abort(403, 'Unauthorized.');
    }

    $request->validate([
        'description' => ['required', 'string', 'max:2000'],
    ]);

    $prompt = file_get_contents(app_path('Prompts/model-suggestion.md'));
    $provider = $this->providerFactory->resolve();

    $response = $provider->generate($prompt, $request->input('description'), 4000);

    $models = json_decode($response->content, true);

    if (!is_array($models)) {
        return response()->json(['models' => []], 200);
    }

    return response()->json(['models' => $models]);
}
```

- [ ] **Step 2: Update routes/api.php**

Replace the entire contents of `routes/api.php` with:

```php
<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\Wizard\WizardController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/templates', [TemplateController::class, 'index']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/auth/me', function () {
        return response()->json(auth()->user());
    });

    // Wizard
    Route::post('/wizard/projects', [WizardController::class, 'store']);
    Route::get('/wizard/projects/{project}', [WizardController::class, 'show']);
    Route::put('/wizard/projects/{project}', [WizardController::class, 'update']);
    Route::post('/wizard/projects/{project}/suggest', [WizardController::class, 'suggest'])
        ->middleware('throttle:20,1');

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    // Generation
    Route::post('/projects/{project}/generate', [GenerationController::class, 'generate'])
        ->middleware('rate_limit_generation');
    Route::get('/projects/{project}/generation', [GenerationController::class, 'status']);
    Route::post('/projects/{project}/regenerate', [GenerationController::class, 'regenerate'])
        ->middleware('rate_limit_generation');
    Route::get('/projects/{project}/preview', [GenerationController::class, 'preview']);
    Route::get('/projects/{project}/preview/{filepath}', [GenerationController::class, 'previewFile'])
        ->where('filepath', '.*');

    // Admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/settings', [AdminController::class, 'settings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);
        Route::get('/stats', [AdminController::class, 'stats']);
    });
});
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add suggest-models endpoint, generation routes, admin routes"
```

---

## Task 13: Feature Tests (Generation + Admin + Suggest)

**Files:**
- Create: `tests/Feature/GenerationTest.php`
- Create: `tests/Feature/AdminTest.php`
- Create: `tests/Feature/SuggestModelsTest.php`

- [ ] **Step 1: Create GenerationTest**

Create `tests/Feature/GenerationTest.php`:

```php
<?php

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Models\User;
use App\Jobs\GenerateProjectJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
    $this->user = User::factory()->create();
});

it('triggers generation and returns 202', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/generate")
        ->assertStatus(202)
        ->assertJsonPath('status', 'generating');

    Queue::assertPushed(GenerateProjectJob::class);
});

it('rejects generation from draft status', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Draft,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/generate")
        ->assertStatus(422);

    Queue::assertNothingPushed();
});

it('returns generation status', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => [
            ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ],
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/generation")
        ->assertOk()
        ->assertJsonPath('status', 'generated')
        ->assertJsonCount(1, 'files');
});

it('returns preview files when generated', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => [
            ['path' => 'CLAUDE.md', 'content' => '# Context'],
            ['path' => 'PROJECT.md', 'content' => '# Project'],
        ],
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/preview")
        ->assertOk()
        ->assertJsonCount(2, 'files');
});

it('returns 404 for preview when not generated', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/preview")
        ->assertNotFound();
});

it('returns single preview file by path', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => [
            ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ],
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/preview/CLAUDE.md")
        ->assertOk()
        ->assertJsonPath('path', 'CLAUDE.md');
});

it('prevents accessing another users generation', function () {
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $other->id]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/generate")
        ->assertForbidden();
});

it('rate limits after 5 generations per hour', function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);

    // Create 5 completed generations in the last hour
    for ($i = 0; $i < 5; $i++) {
        $p = Project::factory()->create(['user_id' => $this->user->id]);
        \App\Models\Generation::create([
            'project_id' => $p->id,
            'prompt_tokens' => 100,
            'completion_tokens' => 100,
            'cost_usd' => 0.01,
            'model' => 'test',
            'provider' => 'anthropic',
            'duration_ms' => 100,
            'cached' => false,
            'created_at' => now(),
        ]);
    }

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/generate")
        ->assertStatus(429);
});

it('regenerate clears cache and dispatches job', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'input_hash' => 'old_hash',
        'generation_output' => [['path' => 'test.md', 'content' => 'old']],
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/regenerate")
        ->assertStatus(202);

    $project->refresh();
    expect($project->input_hash)->toBeNull();
    expect($project->generation_output)->toBeNull();

    Queue::assertPushed(GenerateProjectJob::class);
});
```

- [ ] **Step 2: Create AdminTest**

Create `tests/Feature/AdminTest.php`:

```php
<?php

use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
});

it('returns settings for admin', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/admin/settings')
        ->assertOk()
        ->assertJsonStructure(['ai_provider', 'ai_model', 'ai_max_tokens', 'generation_rate_limit']);
});

it('rejects non-admin from settings', function () {
    $this->actingAs($this->user)
        ->getJson('/api/admin/settings')
        ->assertForbidden();
});

it('updates settings', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/admin/settings', [
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-2.5-pro',
        ])
        ->assertOk()
        ->assertJsonPath('ai_provider', 'gemini')
        ->assertJsonPath('ai_model', 'gemini-2.5-pro');
});

it('validates provider must be anthropic or gemini', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/admin/settings', ['ai_provider' => 'openai'])
        ->assertUnprocessable();
});

it('returns platform stats', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/admin/stats')
        ->assertOk()
        ->assertJsonStructure(['users_count', 'projects_count', 'generations_count', 'total_cost_usd', 'active_provider']);
});
```

- [ ] **Step 3: Create SuggestModelsTest**

Create `tests/Feature/SuggestModelsTest.php`:

```php
<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
    $this->user = User::factory()->create();
});

it('returns model suggestions', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '[{"name":"Task","description":"A task","fields":[{"name":"title","type":"string"}]}]']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]),
    ]);

    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson("/api/wizard/projects/{$project->id}/suggest", [
            'description' => 'A project management tool with tasks and teams',
        ])
        ->assertOk()
        ->assertJsonStructure(['models']);
});

it('requires description', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson("/api/wizard/projects/{$project->id}/suggest", [])
        ->assertUnprocessable();
});
```

- [ ] **Step 4: Run all tests**

```bash
php artisan test
```

Expected: All existing tests still pass + new tests pass.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add tests for generation, admin, and suggest-models endpoints"
```

---

## Task 14: Unit Tests (AI Providers + GenerationService)

**Files:**
- Create: `tests/Feature/AiProviderTest.php`
- Create: `tests/Feature/GenerationServiceTest.php`

- [ ] **Step 1: Create AiProviderTest**

Create `tests/Feature/AiProviderTest.php`:

```php
<?php

use App\Services\AI\AiProviderFactory;
use App\Services\AI\AnthropicProvider;
use App\Services\AI\GeminiProvider;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
});

it('resolves AnthropicProvider by default', function () {
    $factory = app(AiProviderFactory::class);
    $provider = $factory->resolve();

    expect($provider)->toBeInstanceOf(AnthropicProvider::class);
    expect($provider->name())->toBe('anthropic');
});

it('resolves GeminiProvider when settings updated', function () {
    $settings = app(SettingsService::class);
    $settings->set('ai_provider', 'gemini');

    $factory = app(AiProviderFactory::class);
    $provider = $factory->resolve();

    expect($provider)->toBeInstanceOf(GeminiProvider::class);
    expect($provider->name())->toBe('gemini');
});

it('throws for unknown provider', function () {
    $settings = app(SettingsService::class);
    $settings->set('ai_provider', 'openai');

    $factory = app(AiProviderFactory::class);
    $factory->resolve();
})->throws(\InvalidArgumentException::class);

it('AnthropicProvider sends correct headers', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'response']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 200, 'cache_read_input_tokens' => 50],
        ]),
    ]);

    config(['services.anthropic.api_key' => 'test-key']);
    $provider = new AnthropicProvider('test-key', 'claude-sonnet-4-6');
    $response = $provider->generate('system prompt', 'user message', 1000);

    expect($response->content)->toBe('response');
    expect($response->inputTokens)->toBe(100);
    expect($response->outputTokens)->toBe(200);
    expect($response->cacheReadTokens)->toBe(50);

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-api-key', 'test-key')
            && $request->hasHeader('anthropic-beta', 'prompt-caching-2024-07-31');
    });
});

it('GeminiProvider sends correct request', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'gemini response']]]]],
            'usageMetadata' => ['promptTokenCount' => 80, 'candidatesTokenCount' => 150, 'cachedContentTokenCount' => 0],
        ]),
    ]);

    $provider = new GeminiProvider('test-key', 'gemini-2.5-pro');
    $response = $provider->generate('system prompt', 'user message', 1000);

    expect($response->content)->toBe('gemini response');
    expect($response->inputTokens)->toBe(80);
    expect($response->outputTokens)->toBe(150);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'key=test-key')
            && str_contains($request->url(), 'gemini-2.5-pro');
    });
});
```

- [ ] **Step 2: Create GenerationServiceTest**

Create `tests/Feature/GenerationServiceTest.php`:

```php
<?php

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Models\User;
use App\Services\GenerationService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
    $this->user = User::factory()->create();

    // Create a valid sample response
    $this->sampleOutput = implode("\n", [
        '<file path="CLAUDE.md"># Project Context</file>',
        '<file path="PROJECT.md"># Project Overview</file>',
        '<file path="todo.md">- [ ] Task 1</file>',
        '<file path=".claude-reference/architecture.md"># Architecture</file>',
        '<file path=".claude-reference/constants.md"># Constants</file>',
        '<file path=".claude-reference/patterns.md"># Patterns</file>',
        '<file path=".claude-reference/decisions.md"># Decisions</file>',
        '<file path="routes/api.php"><?php Route::get(\'/\', fn() => \'ok\');</file>',
    ]);
});

it('generates and stores output', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $this->sampleOutput]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 5000, 'cache_read_input_tokens' => 500],
        ]),
    ]);

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
        'wizard_data' => ['step_describe' => ['name' => 'TestApp', 'description' => 'Test', 'problem' => 'None']],
    ]);

    $service = app(GenerationService::class);
    $service->generate($project);

    $project->refresh();
    expect($project->status)->toBe(ProjectStatusEnum::Generated);
    expect($project->generation_output)->toBeArray();
    expect(count($project->generation_output))->toBeGreaterThanOrEqual(7);
    expect($project->input_hash)->not->toBeNull();
});

it('uses cache when input hash matches', function () {
    Http::fake(); // Should NOT be called

    $wizardData = ['step_describe' => ['name' => 'CacheTest', 'description' => 'Test', 'problem' => 'None']];
    $inputHash = hash('sha256', json_encode($wizardData));

    // Create a cached project
    Project::factory()->create([
        'user_id' => $this->user->id,
        'input_hash' => $inputHash,
        'generation_output' => [['path' => 'CLAUDE.md', 'content' => 'cached']],
        'status' => ProjectStatusEnum::Generated,
    ]);

    // New project with same wizard data
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
        'wizard_data' => $wizardData,
    ]);

    $service = app(GenerationService::class);
    $service->generate($project);

    $project->refresh();
    expect($project->status)->toBe(ProjectStatusEnum::Generated);
    expect($project->generation_output[0]['content'])->toBe('cached');

    Http::assertNothingSent();
});

it('builds user message correctly from wizard data', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'wizard_data' => [
            'step_describe' => ['name' => 'DentBook', 'description' => 'Dental booking', 'problem' => 'Double bookings'],
            'step_users' => ['app_type' => 'b2b_saas', 'roles' => [['name' => 'admin', 'description' => 'Full access']]],
            'step_models' => ['models' => [['name' => 'Appointment', 'locked' => false, 'description' => 'A booking', 'fields' => [['name' => 'date', 'type' => 'timestamp']]]]],
            'step_auth' => ['multi_tenant' => true, 'auth_method' => 'sanctum', 'guest_access' => false],
            'step_integrations' => ['selected' => ['stripe', 'sms'], 'notes' => 'Test notes'],
        ],
    ]);

    $service = app(GenerationService::class);
    $message = $service->buildUserMessage($project);

    expect($message)->toContain('DentBook');
    expect($message)->toContain('b2b_saas');
    expect($message)->toContain('admin: Full access');
    expect($message)->toContain('Appointment');
    expect($message)->toContain('Multi-tenant: Yes');
    expect($message)->toContain('stripe, sms');
});
```

- [ ] **Step 3: Run all tests**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add unit tests for AI providers, factory, and generation service"
```

---

## Task 15: Final Verification + Cleanup

**Files:**
- Verify all files
- Run full test suite
- Ensure build passes

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: All tests pass (existing Phase 1 tests + new Phase 2A tests).

- [ ] **Step 2: Verify frontend build**

```bash
npm run build
```

Expected: No errors (no frontend changes in Phase 2A, but verify nothing broke).

- [ ] **Step 3: Verify migrations**

```bash
php artisan migrate:fresh --seed
```

Expected: All migrations run, all seeders complete.

- [ ] **Step 4: Verify API endpoints manually**

```bash
php artisan serve &
TOKEN=$(curl -s http://localhost:8000/dev/login | php -r "echo json_decode(file_get_contents('php://stdin'))->token;")

# Admin settings
curl -s http://localhost:8000/api/admin/settings -H "Authorization: Bearer $TOKEN"

# Admin stats
curl -s http://localhost:8000/api/admin/stats -H "Authorization: Bearer $TOKEN"

# Update to Gemini
curl -s -X PUT http://localhost:8000/api/admin/settings \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"ai_provider": "gemini", "ai_model": "gemini-2.5-pro"}'

kill %1 2>/dev/null
```

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: Phase 2A complete — AI generation engine with Anthropic + Gemini support"
```

---

## Summary

| Task | Description | Key Files |
|------|-------------|-----------|
| 1 | Database migrations (settings, is_admin, generation columns) | 3 migrations, model updates |
| 2 | SettingsService + config | SettingsService, services.php, .env.example |
| 3 | AI provider interface + factory | AiProviderInterface, AiResponse, AiProviderFactory |
| 4 | AnthropicProvider | Anthropic API with prompt caching |
| 5 | GeminiProvider | Google Gemini API |
| 6 | OutputParserService + tests | XML parsing, validation, test fixtures |
| 7 | System prompt + model suggestion prompt | app/Prompts/ |
| 8 | GenerationService | Core orchestration: cache, API, parse, validate, store, cost |
| 9 | GenerateProjectJob | Queued async job |
| 10 | Admin middleware + controller | EnsureAdmin, AdminController |
| 11 | RateLimitGeneration + GenerationController | Rate limiting, 5 generation endpoints |
| 12 | Suggest endpoint + routes | WizardController suggest, full route config |
| 13 | Feature tests | Generation, Admin, SuggestModels tests |
| 14 | Unit tests | AI provider, factory, generation service tests |
| 15 | Final verification + cleanup | Full test suite, build, manual verification |
