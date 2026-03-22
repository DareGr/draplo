# Phase 2A — AI Generation Engine + Multi-Provider Architecture Design Spec

**Date:** 2026-03-22
**Status:** Approved
**Scope:** Multi-provider AI interface (Anthropic + Gemini), generation service, output parsing, admin settings, rate limiting, cost tracking, suggest-models endpoint
**Depends on:** Phase 1 (foundation + wizard UI) — completed

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| AI provider architecture | Interface + implementations | `AiProviderInterface` with AnthropicProvider and GeminiProvider. Factory resolves active provider from settings. Future providers just add new implementation. |
| Provider selection | Global admin toggle | Admin picks provider + model in admin panel. All generations use that config. Designed for future per-request routing or fallback. |
| Model selection | Admin picks both provider AND model | Stored as `ai_provider` + `ai_model` in settings. Maximum flexibility for self-hosters. |
| Settings storage | DB overrides .env defaults | `settings` key-value table. Admin panel writes to DB. Falls back to `.env` → `config()` when no DB entry. |
| Prompt strategy | 3-layer with caching | Layer 1 (base) cached, Layer 2 (template) cached per template, Layer 3 (user) unique. Anthropic uses native prompt caching. Gemini uses context caching API. |
| Output format | XML tags | `<file path="...">content</file>` — robust for PHP/JSON content that would break JSON output format. |
| Generation execution | Queued job | `GenerateProjectJob` dispatched to Redis queue. 15-30s API calls would time out synchronous HTTP. Polling endpoint for status. |
| Cache strategy | Input hash | SHA-256 of `wizard_data` JSON. Same input = return cached output. Regenerate endpoint bypasses cache. |

---

## 1. Multi-Provider AI Architecture

### AiProviderInterface

```php
interface AiProviderInterface
{
    public function generate(string $systemPrompt, string $userMessage, int $maxTokens): AiResponse;
    public function name(): string;
    public function supportsCaching(): bool;
}
```

### AiResponse Value Object

```php
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

### AnthropicProvider

- Calls `https://api.anthropic.com/v1/messages`
- Headers: `x-api-key`, `anthropic-version: 2023-06-01`, `anthropic-beta: prompt-caching-2024-07-31`
- Uses `cache_control: { type: 'ephemeral' }` on system prompt blocks for Layer 1 + Layer 2 caching
- Extracts token usage from response: `usage.input_tokens`, `usage.output_tokens`, `usage.cache_read_input_tokens`
- Pricing: configurable per model, defaults for claude-sonnet-4-6: $3/$15 per MTok input/output, cache reads at 10% of input price

### GeminiProvider

- Calls `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`
- Auth: API key as query param `?key={key}`
- System instruction sent via `systemInstruction` field
- Context caching via `cachedContents` API (separate endpoint for creating cached content)
- Extracts token usage from `usageMetadata`: `promptTokenCount`, `candidatesTokenCount`, `cachedContentTokenCount`
- Pricing: configurable per model, defaults for gemini-2.5-pro: $1.25/$10 per MTok

### AiProviderFactory

```php
class AiProviderFactory
{
    public function resolve(): AiProviderInterface
    {
        $provider = app(SettingsService::class)->get('ai_provider', config('services.ai.provider', 'anthropic'));
        $model = app(SettingsService::class)->get('ai_model', config('services.ai.model', 'claude-sonnet-4-6'));

        return match ($provider) {
            'anthropic' => new AnthropicProvider(config('services.anthropic.api_key'), $model),
            'gemini' => new GeminiProvider(config('services.gemini.api_key'), $model),
            default => throw new \InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    }
}
```

### Config (.env defaults)

```env
AI_PROVIDER=anthropic
AI_MODEL=claude-sonnet-4-6
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
```

Added to `config/services.php`:
```php
'ai' => [
    'provider' => env('AI_PROVIDER', 'anthropic'),
    'model' => env('AI_MODEL', 'claude-sonnet-4-6'),
    'max_tokens' => env('AI_MAX_TOKENS', 16000),
],
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
],
'gemini' => [
    'api_key' => env('GEMINI_API_KEY'),
],
```

---

## 2. Settings System + Admin API

### `settings` table migration

| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL PK | |
| key | VARCHAR(100) | UNIQUE |
| value | TEXT | JSON-encoded or plain string |
| timestamps | | |

### SettingsService

- `get(string $key, mixed $default = null)` — checks DB first, falls back to `$default`
- `set(string $key, mixed $value)` — upserts to DB
- `getAiConfig()` — returns `['provider' => string, 'model' => string, 'max_tokens' => int]` resolved from DB → config fallback
- Cached in memory per request via simple property cache (no Redis/file cache — settings rarely change)

### Settings keys

| Key | Default (.env) | Purpose |
|-----|---------------|---------|
| `ai_provider` | `anthropic` | Active AI provider |
| `ai_model` | `claude-sonnet-4-6` | Active model name |
| `ai_max_tokens` | `16000` | Max output tokens |
| `generation_rate_limit` | `5` | Max generations/hour/user |

### Admin middleware + is_admin

New migration: add `is_admin` boolean column to users table (default false).

`EnsureAdmin` middleware: checks `auth()->user()->is_admin`, returns 403 if not.

Dev user seeded with `is_admin: true`.

### Admin API endpoints

**`GET /api/admin/settings`** — returns all settings as key-value object. Admin only.

**`PUT /api/admin/settings`** — bulk update: `{ ai_provider: 'gemini', ai_model: 'gemini-2.5-pro' }`. Admin only. Validates provider is one of `['anthropic', 'gemini']`.

**`GET /api/admin/stats`** — returns: `{ users_count, projects_count, generations_count, total_cost_usd, active_provider, active_model }`. Admin only.

---

## 3. Generation Flow

### GenerationService

Orchestrates the full generation pipeline:

**Step 1 — Build prompt:**
- Layer 1 (base): Read `app/Prompts/system-prompt.md` — static master instructions
- Layer 2 (template): Read `storage/app/templates/{slug}/prompt-context.md` — template-specific context. If no prompt-context.md exists (template not fully built), use empty string.
- Layer 3 (user): Serialize `wizard_data` into structured text

System prompt = Layer 1 + "\n\n" + Layer 2
User message = Layer 3

**Step 2 — Check cache:**
- Compute SHA-256 of `wizard_data` JSON string → `input_hash`
- Check if any project by this user has the same `input_hash` AND has `generation_output` populated
- If cache hit: copy `generation_output` to current project, create Generation record with `cached: true`, set status to `generated`. Return.

**Step 3 — Call AI:**
- Resolve provider via `AiProviderFactory`
- Call `provider->generate(systemPrompt, userMessage, maxTokens)`
- Returns `AiResponse` with content and token counts

**Step 4 — Parse output:**
- `OutputParserService->parse(response->content)` extracts files from XML tags
- Returns `array<['path' => string, 'content' => string]>`

**Step 5 — Validate:**
- At least 7 files: CLAUDE.md, PROJECT.md, todo.md, architecture.md, constants.md, patterns.md, decisions.md
- Migration files (if any) contain `Schema::create`
- `routes/api.php` (if present) contains `Route::`
- No file exceeds 50KB
- If fails: retry once with appended message about validation errors
- If second attempt fails: throw exception (job catches it, sets status to `failed`)

**Step 6 — Store:**
- Save parsed files array to `projects.generation_output`
- Set `projects.input_hash`
- Set `projects.status` to `generated`

**Step 7 — Track cost:**
- Create `Generation` record with:
  - `input_hash`, `prompt_tokens`, `completion_tokens`, `cost_usd`, `model`, `duration_ms`, `cached: false`
- Cost calculation: provider-specific pricing from config
  - Anthropic: `(input/1M * 3) + (output/1M * 15) - (cacheRead/1M * 3 * 0.9)`
  - Gemini: `(input/1M * 1.25) + (output/1M * 10) - (cacheRead/1M * 1.25 * 0.9)`

### GenerateProjectJob

Queued job:
1. Set project status to `generating`
2. Call `GenerationService->generate(project)`
3. On success: status already set to `generated` by service
4. On exception: set status to `failed`, log error
5. Catch all exceptions — never let the job crash without updating status

### Rate Limiting

`RateLimitGeneration` middleware:
- Count generations where `project.user_id = auth.id AND generations.created_at > now() - 1 hour`
- If count >= `SettingsService->get('generation_rate_limit', 5)`: return 429 with `Retry-After` header
- Applied to `POST /projects/{id}/generate` and `POST /projects/{id}/regenerate`

---

## 4. System Prompt + Output Format

### Master system prompt (`app/Prompts/system-prompt.md`)

~3000 tokens. Key sections:
- **Role:** "You are a senior Laravel 12 architect generating a complete project scaffold."
- **Output format:** Wrap each file in `<file path="relative/path">content</file>` XML tags. One tag per file. No content outside tags.
- **Required files (always generate):**
  1. `CLAUDE.md` — Full context for Claude Code agent: project overview, stack, rules, conventions, file naming
  2. `PROJECT.md` — What the app is, features, user roles, glossary, business rules
  3. `todo.md` — Phased development backlog with `- [ ]` checkboxes, at least 3 phases
  4. `.claude-reference/architecture.md` — DB schema tables (markdown tables), API endpoints, file tree
  5. `.claude-reference/constants.md` — Enums, statuses, types, config values
  6. `.claude-reference/patterns.md` — Code patterns, service layer, repository, tenant scoping
  7. `.claude-reference/decisions.md` — Key architectural decisions with rationale
- **Optional files (generate when applicable):**
  8. `database/migrations/*.php` — One file per model, dependency-ordered (no-FK tables first)
  9. `routes/api.php` — Route stubs for all API endpoints
- **Quality rules:**
  - Migrations must be valid PHP with `Schema::create`
  - Use snake_case for columns, PascalCase for models
  - Foreign keys use `{model}_id` convention
  - If multi-tenant: all tenant-scoped models include `tenant_id` FK
  - If integrations selected: include setup instructions in patterns.md
- **Naming convention:** Migration files named `{timestamp}_create_{table}_table.php`

### Model suggestion prompt (`app/Prompts/model-suggestion.md`)

Short prompt (~500 tokens):
- "Given this app description, suggest 5-8 Eloquent models with fields"
- Output format: JSON array of `{name, description, fields: [{name, type}]}`
- Types limited to: string, text, integer, decimal, boolean, timestamp, foreignId, json

### User message construction

`GenerationService` builds Layer 3 from `wizard_data`:

```
## Project
Name: {step_describe.name}
Description: {step_describe.description}
Problem it solves: {step_describe.problem}

## App Type
{step_users.app_type} (e.g., "B2B SaaS")

## User Roles
{for each role in step_users.roles:}
- {role.name}: {role.description}

## Core Models
{for each model in step_models.models:}
### {model.name} {if model.locked: "(locked - required)"}
{model.description}
Fields:
{for each field in model.fields:}
- {field.name} ({field.type})

## Authentication & Tenancy
- Multi-tenant: {step_auth.multi_tenant ? "Yes" : "No"}
- Auth method: {step_auth.auth_method}
- Guest access: {step_auth.guest_access ? "Yes - " + step_auth.guest_description : "No"}

## Integrations
Selected: {step_integrations.selected.join(", ")}
Notes: {step_integrations.notes}
```

### OutputParserService

- Regex: `/<file\s+path="([^"]+)">([\s\S]*?)<\/file>/g`
- Trims whitespace from content
- Returns `array<['path' => string, 'content' => string]>`
- Edge cases: content containing `</file>` literally (unlikely but handled by greedy matching), empty files (valid, stored as empty string)

### Validation (post-parse)

| Check | Rule | On Failure |
|-------|------|------------|
| File count | >= 7 required files present | Retry |
| Migration PHP | Contains `Schema::create` or `Schema::table` | Retry |
| Routes PHP | Contains `Route::` | Retry |
| File size | No file > 50KB | Retry |
| Retry limit | Max 1 retry | Set status to `failed` |

Retry appends to user message: "IMPORTANT: Your previous output was invalid. Missing files: {list}. Please regenerate ALL required files."

---

## 5. API Endpoints + Controllers

### GenerationController

All under `auth:sanctum` middleware. Ownership validated on all endpoints.

**`POST /api/projects/{project}/generate`**
- Middleware: `auth:sanctum`, rate limit
- Validates project status is `wizard_done` (can't generate from `draft`)
- Dispatches `GenerateProjectJob`
- Returns: 202 `{ status: 'generating', message: 'Generation started' }`

**`GET /api/projects/{project}/generation`**
- Returns generation status:
  - `generating`: `{ status: 'generating' }`
  - `generated`: `{ status: 'generated', files: [{path, size}], generation: {prompt_tokens, completion_tokens, cost_usd, model, duration_ms, cached} }`
  - `failed`: `{ status: 'failed', error: 'Validation failed after retry' }`
  - other: `{ status: project.status }`

**`POST /api/projects/{project}/regenerate`**
- Middleware: `auth:sanctum`, rate limit
- Clears `input_hash` and `generation_output` to bypass cache
- Dispatches `GenerateProjectJob`
- Returns: 202

**`GET /api/projects/{project}/preview`**
- Only when status is `generated`
- Returns: `{ files: [{path, content}] }` from `generation_output`

**`GET /api/projects/{project}/preview/{filepath}`**
- Returns single file: `{ path, content }`
- `filepath` uses wildcard routing (`->where('filepath', '.*')`)
- 404 if file not found in generation_output

### AdminController

Under `auth:sanctum` + `admin` middleware.

**`GET /api/admin/settings`**
- Returns: `{ ai_provider, ai_model, ai_max_tokens, generation_rate_limit }`

**`PUT /api/admin/settings`**
- Input: `{ ai_provider?: string, ai_model?: string, ai_max_tokens?: int, generation_rate_limit?: int }`
- Validates `ai_provider` is `in:anthropic,gemini`
- Returns: updated settings

**`GET /api/admin/stats`**
- Returns: `{ users_count, projects_count, generations_count, total_cost_usd, generations_today, active_provider, active_model }`

### Suggest endpoint

**`POST /api/wizard/projects/{project}/suggest`**
- Input: `{ description: string }`
- Calls AI with `model-suggestion.md` prompt + user's description
- Returns: `{ models: [{name, description, fields: [{name, type}]}] }`
- No rate limiting (lightweight prompt, fast response)
- Uses same AiProviderFactory to resolve active provider

### Routes

```php
Route::middleware('auth:sanctum')->group(function () {
    // ... existing wizard + project routes ...

    // Suggest models
    Route::post('/wizard/projects/{project}/suggest', [WizardController::class, 'suggest']);

    // Generation
    Route::post('/projects/{project}/generate', [GenerationController::class, 'generate']);
    Route::get('/projects/{project}/generation', [GenerationController::class, 'status']);
    Route::post('/projects/{project}/regenerate', [GenerationController::class, 'regenerate']);
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

---

## 6. Testing

### Feature/GenerationTest.php
- Trigger generation returns 202, project status becomes `generating`
- Poll status returns `generating` while job runs
- After job completes, status returns `generated` with file list
- Rate limiting: 6th request returns 429
- Regenerate clears cache and starts new generation
- Preview returns files when `generated`, 404 when not
- Can't generate from `draft` status (must be `wizard_done`)
- Ownership validation on all endpoints

### Feature/AdminTest.php
- GET settings returns AI config (admin only)
- PUT settings updates provider/model
- Non-admin gets 403
- Stats returns correct counts

### Feature/SuggestModelsTest.php
- Returns model suggestions from description
- Validates description is required

### Unit/OutputParserServiceTest.php
- Parses valid XML into file array
- Handles PHP code with angle brackets in content
- Returns empty array for malformed input
- Extracts correct paths and content
- Trims whitespace from content

### Unit/GenerationServiceTest.php
- Cache hit skips API call, returns stored output
- Cache miss calls provider
- Cost calculation correct for Anthropic pricing
- Cost calculation correct for Gemini pricing
- Validation catches missing files, retries
- Failed retry sets status to `failed`
- User message is correctly built from wizard_data

### Unit/AiProviderTest.php
- AnthropicProvider builds correct headers (x-api-key, anthropic-beta)
- AnthropicProvider sends cache_control on system prompt
- GeminiProvider builds correct request body
- GeminiProvider sends API key as query param
- AiProviderFactory resolves from DB settings
- AiProviderFactory falls back to .env config
- Unknown provider throws exception

### Mock strategy
- `Http::fake()` for Anthropic/Gemini API responses
- Fixture files in `tests/fixtures/` with sample AI output for parsing tests
- Sample fixture: valid 7-file XML output, ~2000 lines

---

## 7. File Structure

### New files to create

```
app/
├── Contracts/
│   └── AiProviderInterface.php
├── ValueObjects/
│   └── AiResponse.php
├── Providers/
│   ├── AnthropicProvider.php
│   └── GeminiProvider.php
├── Factories/
│   └── AiProviderFactory.php
├── Services/
│   ├── GenerationService.php
│   ├── OutputParserService.php
│   └── SettingsService.php
├── Http/
│   ├── Controllers/
│   │   ├── GenerationController.php
│   │   └── Admin/
│   │       └── AdminController.php
│   └── Middleware/
│       ├── EnsureAdmin.php
│       └── RateLimitGeneration.php
├── Jobs/
│   └── GenerateProjectJob.php
└── Prompts/
    ├── system-prompt.md
    └── model-suggestion.md

database/migrations/
├── xxxx_create_settings_table.php
└── xxxx_add_is_admin_to_users_table.php

database/seeders/
└── SettingsSeeder.php (seeds default AI settings)

tests/
├── Feature/
│   ├── GenerationTest.php
│   ├── AdminTest.php
│   └── SuggestModelsTest.php
├── Unit/
│   ├── OutputParserServiceTest.php
│   ├── GenerationServiceTest.php
│   └── AiProviderTest.php
└── fixtures/
    └── sample-generation-output.xml
```

### Files to modify

```
config/services.php              — add ai, anthropic, gemini sections
.env.example                     — add AI_PROVIDER, AI_MODEL, ANTHROPIC_API_KEY, GEMINI_API_KEY
routes/api.php                   — add generation, admin, suggest routes
app/Models/User.php              — add is_admin attribute
database/seeders/DatabaseSeeder.php — add SettingsSeeder
database/seeders/DevUserSeeder.php  — set is_admin: true
```

---

## 8. Out of Scope (Phase 2A)

- Template `prompt-context.md` files (Phase 2B — Launch Templates)
- 4 remaining template `wizard-defaults.json` (Phase 2B)
- Preview UI in React (Phase 3)
- WebSocket progress updates via Reverb (nice-to-have, polling works for now)
- Skeleton files at `storage/app/skeletons/v1.0.0/` (Phase 3 — needed for export)
- Admin panel React UI (API exists, UI is Phase 5)
- Stripe payment gates (Phase 3)
- GitHub export / ZIP download (Phase 3)
- OpenAI or other AI providers (future — interface supports it)
