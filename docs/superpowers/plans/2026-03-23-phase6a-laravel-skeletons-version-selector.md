# Phase 6A: Laravel Version Skeletons + Wizard Version Selector

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add multi-version Laravel support (10, 11, 12, 13) with pre-built base skeletons and a version selector in the wizard, so that the generation and apply pipeline knows which Laravel version to target.

**Architecture:** Store 4 clean Laravel project skeletons in `storage/app/skeletons/laravel-{version}/`. Add a `laravel_version` column to the projects table. Add a version selector to wizard Step 1. Each skeleton contains a fully working Laravel app + Docker config + GitHub Actions deploy workflow. Templates declare `min_laravel_version` to restrict incompatible selections.

**Tech Stack:** Laravel 12, React 18, PostgreSQL 16, Docker

**Phases overview:**
- **6A (this plan):** Skeletons + version selector + project model changes
- **6B:** Expanded AI generation (models, controllers, seeders, version-aware prompts)
- **6C:** Apply scaffold job (assembles skeleton + AI output + dynamic composer.json)
- **6D:** Auto-deploy pipeline (GitHub Actions, Coolify webhooks, sslip.io)

---

### Task 1: Add `laravel_version` Column to Projects

**Files:**
- Create: `database/migrations/2026_03_23_200000_add_laravel_version_to_projects.php`
- Modify: `app/Models/Project.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('laravel_version', 10)->default('12')->after('template_slug');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('laravel_version');
        });
    }
};
```

- [ ] **Step 2: Update Project model — add to $fillable**

In `app/Models/Project.php`, add `'laravel_version'` to the `$fillable` array.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_03_23_200000_add_laravel_version_to_projects.php app/Models/Project.php
git commit -m "feat: add laravel_version column to projects table"
```

---

### Task 2: Update Wizard Controller to Accept Laravel Version

**Files:**
- Modify: `app/Http/Controllers/Wizard/WizardController.php`
- Modify: `app/Http/Requests/CreateProjectRequest.php`
- Modify: `app/Http/Requests/SaveWizardStepRequest.php`

- [ ] **Step 1: Update CreateProjectRequest — validate laravel_version**

In `app/Http/Requests/CreateProjectRequest.php`, add to rules:

```php
'laravel_version' => ['sometimes', 'string', 'in:10,11,12,13'],
```

- [ ] **Step 2: Update WizardController::store — save laravel_version**

In `app/Http/Controllers/Wizard/WizardController.php`, in the `store()` method, include `laravel_version` when creating the project. Default to `'12'` if not provided:

```php
'laravel_version' => $request->input('laravel_version', '12'),
```

- [ ] **Step 3: Update SaveWizardStepRequest — allow version change in step_describe**

In `app/Http/Requests/SaveWizardStepRequest.php`, add `'data.laravel_version'` as an optional field when `step === 'describe'`:

```php
'data.laravel_version' => ['sometimes', 'string', 'in:10,11,12,13'],
```

- [ ] **Step 4: Update WizardController::update — save version on describe step**

In the `update()` method, when `$step === 'describe'`, also update `laravel_version` if present in data:

```php
if ($step === 'describe' && $request->has('data.laravel_version')) {
    $project->laravel_version = $request->input('data.laravel_version');
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Wizard/WizardController.php app/Http/Requests/CreateProjectRequest.php app/Http/Requests/SaveWizardStepRequest.php
git commit -m "feat: accept laravel_version in wizard create and update"
```

---

### Task 3: Add Version Selector to Wizard Step 1 (React)

**Files:**
- Modify: `resources/js/pages/Wizard/StepDescribe.jsx`

- [ ] **Step 1: Add version selector UI**

In `resources/js/pages/Wizard/StepDescribe.jsx`, add a version selector after the existing form fields. Use pill-style buttons similar to the app_type selector in StepUsers:

```jsx
{/* Laravel Version */}
<div className="mt-6">
    <label className="text-sm font-label text-on-surface-variant uppercase tracking-widest mb-2 block">
        Laravel Version
    </label>
    <div className="flex gap-2">
        {['10', '11', '12', '13'].map((v) => (
            <button
                key={v}
                type="button"
                onClick={() => onChange({ ...data, laravel_version: v })}
                className={`px-4 py-2 rounded-md font-mono text-sm transition-colors ${
                    (data.laravel_version || '12') === v
                        ? 'bg-primary/15 text-primary border border-primary/30'
                        : 'bg-surface-container-high text-on-surface-variant border border-outline-variant/10 hover:bg-surface-container-highest'
                }`}
            >
                {v}{v === '13' ? ' ★' : ''}
            </button>
        ))}
    </div>
    <p className="text-xs text-on-surface-variant mt-1.5 font-mono">
        {(data.laravel_version || '12') === '13' && 'Recommended — latest features'}
        {(data.laravel_version || '12') === '12' && 'Stable — well tested, wide ecosystem'}
        {(data.laravel_version || '12') === '11' && 'Simplified structure — no Kernel.php'}
        {(data.laravel_version || '12') === '10' && 'Legacy — PHP 8.1+ compatible'}
    </p>
</div>
```

- [ ] **Step 2: Include laravel_version when saving step data**

Ensure the `onChange` handler passes `laravel_version` as part of the step data. Check how `StepDescribe` currently handles `onChange` and ensure `laravel_version` is included in the data object sent to the API.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Wizard/StepDescribe.jsx
git commit -m "feat: add Laravel version selector to wizard Step 1"
```

---

### Task 4: Add min_laravel_version to Templates

**Files:**
- Modify: `database/seeders/TemplateSeeder.php`
- Modify: `app/Services/TemplateService.php`

- [ ] **Step 1: Add min_laravel_version to template data**

In `database/seeders/TemplateSeeder.php`, add `'min_laravel_version' => '10'` (or higher) to each template definition. Most templates should work with all versions (`'10'`). If a template uses features requiring Laravel 11+ (e.g., simplified routing), set `'12'` or `'11'`.

Default for all existing templates: `'min_laravel_version' => '10'`.

- [ ] **Step 2: Update TemplateService to include min_laravel_version**

In `app/Services/TemplateService.php`, ensure `listTemplates()` and `getTemplate()` return the `min_laravel_version` field from template.json.

- [ ] **Step 3: Update React TemplateLibrary**

In `resources/js/pages/TemplateLibrary.jsx`, when user has already selected a Laravel version (from a previous project or default), show a tooltip on templates that require a higher version: "Requires Laravel {min_version}+". This is optional for MVP — templates can just always be clickable.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/TemplateSeeder.php app/Services/TemplateService.php
git commit -m "feat: add min_laravel_version to template metadata"
```

---

### Task 5: Create Base Laravel Skeletons Directory Structure

**Files:**
- Create: `storage/app/skeletons/README.md`
- Create: `storage/app/skeletons/.gitkeep`

- [ ] **Step 1: Create skeletons directory**

```bash
mkdir -p storage/app/skeletons
```

- [ ] **Step 2: Create README explaining the skeleton system**

Create `storage/app/skeletons/README.md`:

```markdown
# Laravel Base Skeletons

Each directory contains a clean Laravel project for that version:

- `laravel-10/` — Laravel 10.x, PHP 8.1+
- `laravel-11/` — Laravel 11.x, PHP 8.2+
- `laravel-12/` — Laravel 12.x, PHP 8.3+
- `laravel-13/` — Laravel 13.x, PHP 8.3+

## How skeletons are used

When a user clicks "Apply", the ApplyScaffoldJob:
1. Copies the matching skeleton to a temp directory
2. Injects AI-generated files (models, controllers, migrations, etc.)
3. Modifies composer.json with selected dependencies
4. Pushes everything to GitHub

## Updating skeletons

Run `php artisan skeleton:refresh` to regenerate all skeletons
from fresh `composer create-project` outputs. Or manually update
by running `composer create-project` for each version.

## What's included in each skeleton

- Full Laravel project structure
- Dockerfile (multi-stage build)
- docker-compose.yml (app + postgres + redis)
- docker/nginx.conf, docker/entrypoint.sh
- .github/workflows/deploy.yml (Coolify auto-deploy)
- .env.example (Docker service hostnames)
- .dockerignore
```

- [ ] **Step 3: Commit**

```bash
git add storage/app/skeletons/
git commit -m "feat: create skeletons directory structure with README"
```

---

### Task 6: Create Laravel 12 Base Skeleton

**Files:**
- Create: `storage/app/skeletons/laravel-12/` (full Laravel project)

This is the most important skeleton — Laravel 12 is the default.

- [ ] **Step 1: Generate clean Laravel 12 project**

Run locally (or in a temp container):
```bash
composer create-project laravel/laravel storage/app/skeletons/laravel-12 "12.*" --prefer-dist --no-interaction
```

Remove unnecessary files from the skeleton:
```bash
cd storage/app/skeletons/laravel-12
rm -rf vendor node_modules .git .env tests
rm -rf storage/logs/*.log storage/framework/cache/data/*
```

- [ ] **Step 2: Add Dockerfile to skeleton**

Create `storage/app/skeletons/laravel-12/Dockerfile`:

```dockerfile
# --- Build frontend ---
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# --- Build PHP app ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-autoloader --no-scripts --prefer-dist --ignore-platform-req=ext-pcntl
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Production image ---
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor postgresql-dev icu-dev \
    && docker-php-ext-install pdo_pgsql intl pcntl opcache \
    && rm -rf /var/cache/apk/*

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build-deps

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-app.ini"

RUN mkdir -p /var/run/nginx /var/log/supervisor

WORKDIR /var/www
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN rm -rf node_modules .git tests \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s CMD wget -qO- http://localhost/up || exit 1
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
```

- [ ] **Step 3: Add docker-compose.yml to skeleton**

Create `storage/app/skeletons/laravel-12/docker-compose.yml`:

```yaml
services:
  app:
    build: .
    restart: unless-stopped
    ports:
      - "${APP_PORT:-80}:80"
    env_file: .env
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy

  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE:-app}
      POSTGRES_USER: ${DB_USERNAME:-app}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-app}"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  postgres_data:
```

- [ ] **Step 4: Add Docker support files to skeleton**

Create `storage/app/skeletons/laravel-12/docker/nginx.conf` (same as Draplo's own nginx.conf — standard Laravel Nginx config with PHP-FPM, gzip, security headers, Vite asset caching).

Create `storage/app/skeletons/laravel-12/docker/entrypoint.sh`:
```bash
#!/bin/sh
set -e
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link 2>/dev/null || true
exec "$@"
```

Create `storage/app/skeletons/laravel-12/docker/supervisord.conf` (Nginx + PHP-FPM).

Create `storage/app/skeletons/laravel-12/docker/php.ini` (OPcache, JIT, upload limits).

Create `storage/app/skeletons/laravel-12/.dockerignore`.

- [ ] **Step 5: Add GitHub Actions deploy workflow to skeleton**

Create `storage/app/skeletons/laravel-12/.github/workflows/deploy.yml`:

```yaml
name: Deploy to Coolify

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Trigger Coolify Deploy
        run: |
          curl -X POST \
            -H "Authorization: Bearer ${{ secrets.COOLIFY_TOKEN }}" \
            -H "Content-Type: application/json" \
            "${{ secrets.COOLIFY_WEBHOOK_URL }}"
```

- [ ] **Step 6: Update skeleton .env.example for Docker**

Replace the skeleton's `.env.example` with Docker-ready defaults:
```env
APP_NAME=MyApp
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=secret

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

- [ ] **Step 7: Commit**

```bash
git add storage/app/skeletons/laravel-12/
git commit -m "feat: add Laravel 12 base skeleton with Docker + GitHub Actions"
```

---

### Task 7: Create Laravel 13, 11, and 10 Skeletons

**Files:**
- Create: `storage/app/skeletons/laravel-13/`
- Create: `storage/app/skeletons/laravel-11/`
- Create: `storage/app/skeletons/laravel-10/`

- [ ] **Step 1: Create Laravel 13 skeleton**

Copy laravel-12 as base, update `composer.json` version constraints:
```bash
cp -r storage/app/skeletons/laravel-12 storage/app/skeletons/laravel-13
```
Update `composer.json`: `"laravel/framework": "^13.0"` (when available, otherwise keep as `^12.0` placeholder).

- [ ] **Step 2: Create Laravel 11 skeleton**

```bash
composer create-project laravel/laravel storage/app/skeletons/laravel-11 "11.*" --prefer-dist --no-interaction
```
Clean up (remove vendor, node_modules, .git, .env, tests, logs). Copy Docker files from laravel-12 skeleton. Note: Laravel 11 has `bootstrap/app.php` for middleware/routes (no Kernel.php).

- [ ] **Step 3: Create Laravel 10 skeleton**

```bash
composer create-project laravel/laravel storage/app/skeletons/laravel-10 "10.*" --prefer-dist --no-interaction
```
Clean up. Copy Docker files. Update Dockerfile to use `php:8.1-fpm-alpine` for PHP 8.1 compatibility. Note: Laravel 10 has `app/Http/Kernel.php` and `app/Providers/RouteServiceProvider.php`.

- [ ] **Step 4: Commit each skeleton**

```bash
git add storage/app/skeletons/laravel-13/ && git commit -m "feat: add Laravel 13 base skeleton"
git add storage/app/skeletons/laravel-11/ && git commit -m "feat: add Laravel 11 base skeleton"
git add storage/app/skeletons/laravel-10/ && git commit -m "feat: add Laravel 10 base skeleton"
```

---

### Task 8: Add SkeletonService

**Files:**
- Create: `app/Services/SkeletonService.php`
- Create: `tests/Feature/SkeletonServiceTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

use App\Services\SkeletonService;

test('getSkeletonPath returns correct path for valid version', function () {
    $service = new SkeletonService();
    $path = $service->getSkeletonPath('12');
    expect($path)->toEndWith('skeletons/laravel-12');
    expect(is_dir($path))->toBeTrue();
});

test('getSkeletonPath throws for invalid version', function () {
    $service = new SkeletonService();
    $service->getSkeletonPath('99');
})->throws(\InvalidArgumentException::class);

test('getSupportedVersions returns all four versions', function () {
    $service = new SkeletonService();
    expect($service->getSupportedVersions())->toBe(['10', '11', '12', '13']);
});

test('getPhpVersion returns correct PHP for each Laravel version', function () {
    $service = new SkeletonService();
    expect($service->getPhpVersion('10'))->toBe('8.1');
    expect($service->getPhpVersion('11'))->toBe('8.2');
    expect($service->getPhpVersion('12'))->toBe('8.3');
    expect($service->getPhpVersion('13'))->toBe('8.3');
});
```

- [ ] **Step 2: Implement SkeletonService**

Create `app/Services/SkeletonService.php`:

```php
<?php

namespace App\Services;

use InvalidArgumentException;

class SkeletonService
{
    private const VERSIONS = ['10', '11', '12', '13'];

    private const PHP_VERSIONS = [
        '10' => '8.1',
        '11' => '8.2',
        '12' => '8.3',
        '13' => '8.3',
    ];

    public function getSupportedVersions(): array
    {
        return self::VERSIONS;
    }

    public function getSkeletonPath(string $version): string
    {
        if (!in_array($version, self::VERSIONS, true)) {
            throw new InvalidArgumentException("Unsupported Laravel version: {$version}");
        }

        $path = storage_path("app/skeletons/laravel-{$version}");

        if (!is_dir($path)) {
            throw new InvalidArgumentException("Skeleton not found for Laravel {$version}");
        }

        return $path;
    }

    public function getPhpVersion(string $laravelVersion): string
    {
        return self::PHP_VERSIONS[$laravelVersion]
            ?? throw new InvalidArgumentException("Unknown version: {$laravelVersion}");
    }

    public function isVersionCompatible(string $selectedVersion, string $minVersion): bool
    {
        return (int) $selectedVersion >= (int) $minVersion;
    }
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=SkeletonService`
Expected: All pass.

- [ ] **Step 4: Commit**

```bash
git add app/Services/SkeletonService.php tests/Feature/SkeletonServiceTest.php
git commit -m "feat: add SkeletonService for multi-version Laravel support"
```

---

### Task 9: Update API to Return laravel_version

**Files:**
- Modify: `app/Http/Controllers/Wizard/WizardController.php`
- Modify: `app/Http/Controllers/GenerationController.php`

- [ ] **Step 1: Verify project serialization includes laravel_version**

Since `laravel_version` was added to `$fillable` and the controllers return full project objects, it should already be included in JSON responses. Verify by checking that `WizardController::show()` and `GenerationController::status()` return projects with `laravel_version`.

If the project uses `$hidden` or a Resource class that filters fields, add `laravel_version` to the returned data.

- [ ] **Step 2: Add version to config/flags endpoint**

In `app/Http/Controllers/ConfigController.php`, add supported Laravel versions to the flags response:

```php
'supported_laravel_versions' => ['10', '11', '12', '13'],
'default_laravel_version' => '12',
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/ConfigController.php
git commit -m "feat: expose supported Laravel versions in config API"
```

---

### Task 10: Update Documentation

**Files:**
- Modify: `CLAUDE.md`
- Modify: `todo.md`

- [ ] **Step 1: Update CLAUDE.md**

Add to the Project Overview section:
```
**Laravel version support:** Users choose Laravel 10, 11, 12, or 13 in the wizard. Each version has a pre-built skeleton in `storage/app/skeletons/`. The skeleton is used as the base when assembling the final project.
```

- [ ] **Step 2: Update todo.md**

Add Phase 6 section:
```markdown
### Phase 6A — Multi-Version Laravel Skeletons (Complete)
- [x] Add laravel_version column to projects table
- [x] Add version selector to wizard Step 1
- [x] Create base skeletons for Laravel 10, 11, 12, 13
- [x] Add SkeletonService
- [x] Add min_laravel_version to templates

### Phase 6B — Expanded AI Generation
- [ ] Expand system prompt to generate runnable code (models, controllers, requests, seeders)
- [ ] Add version-specific prompt sections (Kernel vs bootstrap/app.php)
- [ ] Update OutputParserService required files list
- [ ] Update validation for new file types

### Phase 6C — Apply Scaffold Job
- [ ] Create ApplyScaffoldJob (assemble skeleton + AI output + composer.json)
- [ ] Create ComposerJsonService (dynamic package list from wizard selections)
- [ ] Add "Apply" button to Preview UI
- [ ] Add new project status: Applying → Applied

### Phase 6D — Auto-Deploy Pipeline
- [ ] Set GitHub repo secrets via API (COOLIFY_TOKEN, COOLIFY_WEBHOOK_URL)
- [ ] Configure Coolify webhook endpoint
- [ ] Auto-assign sslip.io domain
- [ ] Add custom domain configuration UI
```

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md todo.md
git commit -m "docs: add Phase 6 multi-version skeleton documentation"
```
