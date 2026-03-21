# Phase 1 — Foundation + Wizard UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a working Laravel 12 + React 18 application with a 6-step wizard for describing SaaS projects, a Template Library page, and API persistence — the foundation for Draplo's AI scaffold generation.

**Architecture:** Laravel 12 API backend with Sanctum auth, PostgreSQL 16, Redis 7 via Docker Compose. React 18 SPA (mounted via Blade) with React Router v6 for the wizard flow. Wizard state persists to `projects.wizard_data` JSONB column via REST API. Templates stored as JSON files on disk, loaded by TemplateService.

**Tech Stack:** Laravel 12, PHP 8.3+, React 18, Tailwind CSS 4 (@tailwindcss/vite), Vite, PostgreSQL 16, Redis 7, Sanctum, Horizon, Pest PHP, React Router v6, Axios

**Spec:** `docs/superpowers/specs/2026-03-21-phase1-foundation-wizard-design.md`

---

## File Structure

### Backend (Laravel)

```
app/
├── Enums/
│   ├── UserPlanEnum.php              — free, paid, subscriber
│   └── ProjectStatusEnum.php         — draft, wizard_done, generating, generated, exported, deploying, deployed, failed
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── DevLoginController.php    — GET /dev/login (local env only)
│   │   ├── TemplateController.php        — GET /api/templates
│   │   ├── Wizard/
│   │   │   └── WizardController.php      — CRUD for wizard projects
│   │   └── ProjectController.php         — GET /api/projects, DELETE /api/projects/{id}
│   └── Requests/
│       ├── CreateProjectRequest.php      — validates template_slug
│       └── SaveWizardStepRequest.php     — validates step + data per step
├── Models/
│   ├── User.php                          — plan enum cast, github_token encrypted, relationships
│   ├── Project.php                       — JSONB casts, status enum, relationships
│   └── Generation.php                    — cost tracking, relationships
└── Services/
    └── TemplateService.php               — loads template.json + wizard-defaults.json from disk

database/
├── migrations/
│   ├── xxxx_create_users_table.php
│   ├── xxxx_create_projects_table.php
│   └── xxxx_create_generations_table.php
└── seeders/
    ├── DatabaseSeeder.php
    ├── DevUserSeeder.php                 — test user for dev-mode auth
    └── TemplateSeeder.php                — copies template JSON files to storage

routes/
├── api.php                               — all API routes
└── web.php                               — landing placeholder, SPA catch-all, dev login

config/
└── cors.php                              — allow Vite dev origin

storage/app/templates/
└── booking-platform/
    ├── template.json                     — metadata (name, slug, category, etc.)
    └── wizard-defaults.json              — pre-populated wizard data for all steps
```

### Frontend (React SPA)

```
resources/
├── views/
│   ├── app.blade.php                     — React SPA mount point
│   └── landing.blade.php                 — placeholder landing page
├── css/
│   └── app.css                           — Tailwind imports + design tokens
└── js/
    ├── app.jsx                           — React entry point, router, auth bootstrap
    ├── api.js                            — Axios instance with auth header
    ├── pages/
    │   ├── TemplateLibrary.jsx           — template grid with category filter
    │   ├── ProjectList.jsx               — list user's projects
    │   ├── NotFound.jsx                  — 404 page
    │   └── Wizard/
    │       ├── WizardLayout.jsx          — step nav, bottom bar, progress, loads project
    │       ├── StepDescribe.jsx          — step 1: name, description, problem
    │       ├── StepUsers.jsx             — step 2: app type, roles
    │       ├── StepModels.jsx            — step 3: model cards with fields
    │       ├── StepAuth.jsx              — step 4: multi-tenant, auth method, guest access
    │       ├── StepIntegrations.jsx      — step 5: integration toggles
    │       └── StepReview.jsx            — step 6: summary, wizard_done status
    ├── components/
    │   ├── AppLayout.jsx                 — sidebar + top bar + content area
    │   ├── Sidebar.jsx                   — nav items, wizard progress widget
    │   ├── TopBar.jsx                    — logo, nav links, auth buttons
    │   ├── Button.jsx                    — primary/secondary/tertiary variants
    │   ├── Input.jsx                     — monospace, ghost border, focus glow
    │   ├── Textarea.jsx                  — same styling as Input, multi-line
    │   ├── Card.jsx                      — tonal layering, hover shift
    │   ├── Chip.jsx                      — status dot + monospace label
    │   ├── Toggle.jsx                    — on/off switch
    │   ├── Toast.jsx                     — error/success notifications
    │   └── CategoryFilter.jsx            — horizontal scrollable pills
    └── hooks/
        └── useProject.js                 — load project data, save step, shared across wizard steps

tests/
├── Feature/
│   ├── AuthTest.php                      — dev login works in local, blocked in production
│   ├── WizardTest.php                    — create/update/load/delete projects, ownership, validation
│   └── TemplateTest.php                  — GET /api/templates returns template list
└── Unit/
    └── TemplateServiceTest.php           — loads defaults, lists templates, handles unknown slugs
```

### Config Files (Root)

```
docker-compose.yml                        — postgres, redis services
vite.config.js                            — laravel + react + tailwindcss plugins
.env.example                              — all env vars documented
```

---

## Task 1: Laravel 12 Project Scaffold

**Files:**
- Create: entire Laravel 12 project structure via installer
- Modify: `composer.json` (add Horizon, Sanctum)
- Create: `docker-compose.yml`
- Modify: `.env.example`, `.env`

- [ ] **Step 1: Create Laravel 12 project**

Run from the PARENT directory of `draplo` (since the directory already has docs in it, we create the Laravel project in a temp dir then move files):

```bash
cd /c
composer create-project laravel/laravel draplo-laravel --prefer-dist
```

Then move Laravel files into the existing `draplo` directory (preserving existing docs):

```bash
# Move all Laravel files into draplo, skipping existing files
cp -rn /c/draplo-laravel/* /c/draplo/
cp -rn /c/draplo-laravel/.* /c/draplo/ 2>/dev/null
rm -rf /c/draplo-laravel
```

- [ ] **Step 2: Create docker-compose.yml**

```yaml
services:
  postgres:
    image: postgres:16
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: draplo
      POSTGRES_USER: draplo
      POSTGRES_PASSWORD: draplo
    volumes:
      - postgres_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

volumes:
  postgres_data:
```

- [ ] **Step 3: Install Laravel packages**

```bash
cd /c/draplo
composer require laravel/sanctum laravel/horizon
php artisan install:api
php artisan horizon:install
```

- [ ] **Step 4: Configure .env for Docker services**

Update `.env` with PostgreSQL and Redis connection:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=draplo
DB_USERNAME=draplo
DB_PASSWORD=draplo

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Update `.env.example` with the same values (passwords blanked) plus feature flags:

```env
STRIPE_ENABLED=true
COOLIFY_ENABLED=true
GITHUB_ENABLED=true
PREMIUM_TEMPLATES_ENABLED=true
THREEJS_HERO_ENABLED=true
```

- [ ] **Step 5: Verify Docker + Laravel boot**

```bash
docker-compose up -d
php artisan migrate
php artisan serve &
curl -s http://localhost:8000 | head -5
```

Expected: Laravel welcome page HTML.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: scaffold Laravel 12 project with Docker Compose (PostgreSQL 16, Redis 7)"
```

---

## Task 2: Vite + React 18 + Tailwind CSS 4 Setup

**Files:**
- Modify: `package.json` (add React, Tailwind, React Router)
- Modify: `vite.config.js` (add React + Tailwind plugins)
- Create: `resources/css/app.css` (Tailwind imports + design tokens)
- Create: `resources/js/app.jsx` (React entry point)
- Create: `resources/views/app.blade.php` (SPA mount)
- Modify: `resources/views/landing.blade.php` (placeholder)
- Modify: `routes/web.php` (SPA catch-all)

- [ ] **Step 1: Install npm dependencies**

```bash
cd /c/draplo
npm install react@18 react-dom@18 react-router-dom@6 axios
npm install -D @vitejs/plugin-react @tailwindcss/vite
```

- [ ] **Step 2: Configure vite.config.js**

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
});
```

- [ ] **Step 3: Create resources/css/app.css with design system tokens**

```css
@import "tailwindcss";

@source "../views/**/*.blade.php";
@source "../js/**/*.jsx";

@theme {
    --color-background: #121316;
    --color-surface: #121316;
    --color-surface-dim: #121316;
    --color-surface-container-lowest: #0d0e11;
    --color-surface-container-low: #1b1b1f;
    --color-surface-container: #1f1f23;
    --color-surface-container-high: #292a2d;
    --color-surface-container-highest: #343538;
    --color-surface-variant: #343538;
    --color-surface-bright: #38393d;
    --color-surface-tint: #c0c1ff;

    --color-primary: #c0c1ff;
    --color-primary-container: #8083ff;
    --color-primary-fixed: #e1e0ff;
    --color-primary-fixed-dim: #c0c1ff;
    --color-on-primary: #1000a9;
    --color-on-primary-container: #0d0096;
    --color-on-primary-fixed: #07006c;
    --color-on-primary-fixed-variant: #2f2ebe;
    --color-inverse-primary: #494bd6;

    --color-secondary: #4cd7f6;
    --color-secondary-container: #03b5d3;
    --color-secondary-fixed: #acedff;
    --color-secondary-fixed-dim: #4cd7f6;
    --color-on-secondary: #003640;
    --color-on-secondary-container: #00424e;
    --color-on-secondary-fixed: #001f26;
    --color-on-secondary-fixed-variant: #004e5c;

    --color-tertiary: #ffb4a8;
    --color-tertiary-container: #ff5542;
    --color-tertiary-fixed: #ffdad5;
    --color-tertiary-fixed-dim: #ffb4a8;
    --color-on-tertiary: #690001;
    --color-on-tertiary-container: #5c0000;
    --color-on-tertiary-fixed: #410000;
    --color-on-tertiary-fixed-variant: #930001;

    --color-on-background: #e3e2e6;
    --color-on-surface: #e3e2e6;
    --color-on-surface-variant: #c7c4d7;
    --color-outline: #908fa0;
    --color-outline-variant: #464554;
    --color-inverse-surface: #e3e2e6;
    --color-inverse-on-surface: #2f3034;

    --color-error: #ffb4ab;
    --color-error-container: #93000a;
    --color-on-error: #690005;
    --color-on-error-container: #ffdad6;

    --font-headline: "Inter", sans-serif;
    --font-body: "Inter", sans-serif;
    --font-label: "Space Grotesk", sans-serif;
    --font-mono: "Berkeley Mono", monospace;
}
```

- [ ] **Step 4: Create resources/views/app.blade.php**

```blade
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Draplo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="bg-background text-on-background font-body antialiased selection:bg-primary-container/30">
    <div id="app"></div>
</body>
</html>
```

- [ ] **Step 5: Create resources/js/app.jsx (minimal React mount)**

```jsx
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';

function App() {
    return (
        <div className="min-h-screen flex items-center justify-center">
            <h1 className="text-4xl font-extrabold text-primary tracking-tight">Draplo</h1>
        </div>
    );
}

const root = createRoot(document.getElementById('app'));
root.render(
    <BrowserRouter>
        <Routes>
            <Route path="*" element={<App />} />
        </Routes>
    </BrowserRouter>
);
```

- [ ] **Step 6: Update routes/web.php for SPA catch-all**

```php
<?php

use Illuminate\Support\Facades\Route;

// Landing page (Blade, for SEO — Phase 5)
Route::get('/', function () {
    return view('landing');
});

// React SPA catch-all — serves app.blade.php for all SPA routes
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api|dev|sanctum|horizon).*$');
```

- [ ] **Step 7: Create a placeholder landing.blade.php**

```blade
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Draplo — Draft it. Deploy it.</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-background text-on-background font-body antialiased">
    <div class="min-h-screen flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-6xl font-extrabold text-primary tracking-tight mb-4">Draplo</h1>
            <p class="text-on-surface-variant text-lg">Draft it. Deploy it.</p>
            <a href="/templates" class="mt-8 inline-block px-8 py-3 bg-gradient-to-r from-primary to-primary-container text-on-primary font-bold rounded-md">
                Get Started
            </a>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 8: Verify React + Tailwind renders**

```bash
npm run dev &
php artisan serve &
# Visit http://localhost:8000/templates in browser
# Should see "Draplo" text in primary purple color
```

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add Vite + React 18 + Tailwind CSS 4 with design system tokens"
```

---

## Task 3: Database Migrations, Enums, and Models

**Files:**
- Create: `app/Enums/UserPlanEnum.php`
- Create: `app/Enums/ProjectStatusEnum.php`
- Create: `database/migrations/xxxx_create_users_table.php` (modify existing)
- Create: `database/migrations/xxxx_create_projects_table.php`
- Create: `database/migrations/xxxx_create_generations_table.php`
- Modify: `app/Models/User.php`
- Create: `app/Models/Project.php`
- Create: `app/Models/Generation.php`

- [ ] **Step 1: Create UserPlanEnum**

Create `app/Enums/UserPlanEnum.php`:

```php
<?php

namespace App\Enums;

enum UserPlanEnum: string
{
    case Free = 'free';
    case Paid = 'paid';
    case Subscriber = 'subscriber';
}
```

- [ ] **Step 2: Create ProjectStatusEnum**

Create `app/Enums/ProjectStatusEnum.php`:

```php
<?php

namespace App\Enums;

enum ProjectStatusEnum: string
{
    case Draft = 'draft';
    case WizardDone = 'wizard_done';
    case Generating = 'generating';
    case Generated = 'generated';
    case Exported = 'exported';
    case Deploying = 'deploying';
    case Deployed = 'deployed';
    case Failed = 'failed';
}
```

- [ ] **Step 3: Modify the users migration**

Laravel 12 creates a default users migration. Modify it to match the spec schema:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('github_id', 100)->nullable();
            $table->text('github_token')->nullable();
            $table->string('github_username', 100)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->string('stripe_customer_id', 100)->nullable();
            $table->string('plan', 50)->default('free');
            $table->timestamp('paid_at')->nullable();
            $table->integer('generation_count')->default(0);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
```

- [ ] **Step 4: Create projects migration**

```bash
php artisan make:migration create_projects_table
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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 100);
            $table->string('template_slug', 100)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('wizard_data')->nullable();
            $table->jsonb('generation_output')->nullable();
            $table->string('skeleton_version', 20)->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->string('github_repo_url', 500)->nullable();
            $table->string('github_repo_name', 200)->nullable();
            $table->string('coolify_app_id', 100)->nullable();
            $table->string('coolify_db_id', 100)->nullable();
            $table->string('deploy_url', 500)->nullable();
            $table->string('custom_domain', 255)->nullable();
            $table->string('status', 50)->default('draft');
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_projects_user');
            $table->index('input_hash', 'idx_projects_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

- [ ] **Step 5: Create generations migration**

```bash
php artisan make:migration create_generations_table
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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('input_hash', 64)->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->decimal('cost_usd', 8, 4)->nullable();
            $table->string('model', 100)->nullable();
            $table->integer('duration_ms')->nullable();
            $table->boolean('cached')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
```

- [ ] **Step 6: Update User model**

```php
<?php

namespace App\Models;

use App\Enums\UserPlanEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'github_id',
        'github_token',
        'github_username',
        'avatar_url',
        'stripe_customer_id',
        'plan',
        'paid_at',
        'generation_count',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'github_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'plan' => UserPlanEnum::class,
            'paid_at' => 'datetime',
            'github_token' => 'encrypted',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function isFree(): bool
    {
        return $this->plan === UserPlanEnum::Free;
    }

    public function isPaid(): bool
    {
        return $this->plan === UserPlanEnum::Paid || $this->plan === UserPlanEnum::Subscriber;
    }

    public function isSubscriber(): bool
    {
        return $this->plan === UserPlanEnum::Subscriber;
    }
}
```

- [ ] **Step 7: Create Project model**

Create `app/Models/Project.php`:

```php
<?php

namespace App\Models;

use App\Enums\ProjectStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'template_slug',
        'description',
        'wizard_data',
        'generation_output',
        'skeleton_version',
        'input_hash',
        'github_repo_url',
        'github_repo_name',
        'coolify_app_id',
        'coolify_db_id',
        'deploy_url',
        'custom_domain',
        'status',
        'exported_at',
        'deployed_at',
    ];

    protected function casts(): array
    {
        return [
            'wizard_data' => 'array',
            'generation_output' => 'array',
            'status' => ProjectStatusEnum::class,
            'exported_at' => 'datetime',
            'deployed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }
}
```

- [ ] **Step 8: Create Generation model**

Create `app/Models/Generation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'input_hash',
        'prompt_tokens',
        'completion_tokens',
        'cost_usd',
        'model',
        'duration_ms',
        'cached',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:4',
            'cached' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
```

- [ ] **Step 9: Update UserFactory for custom fields**

Modify `database/factories/UserFactory.php` (Laravel ships a default one):

```php
<?php

namespace Database\Factories;

use App\Enums\UserPlanEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => null,
            'github_id' => (string) fake()->unique()->numberBetween(10000, 99999),
            'github_username' => fake()->userName(),
            'avatar_url' => fake()->imageUrl(200, 200),
            'plan' => UserPlanEnum::Free,
            'generation_count' => 0,
        ];
    }
}
```

- [ ] **Step 10: Run migrations to verify**

```bash
php artisan migrate:fresh
```

Expected: All tables created without errors.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: add database migrations, enums, and models (User, Project, Generation)"
```

---

## Task 4: Dev-Mode Auth + Seeders + Template Data

**Files:**
- Create: `app/Http/Controllers/Auth/DevLoginController.php`
- Create: `database/seeders/DevUserSeeder.php`
- Create: `database/seeders/TemplateSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `storage/app/templates/booking-platform/template.json`
- Create: `storage/app/templates/booking-platform/wizard-defaults.json`
- Modify: `routes/web.php` (add dev login route)
- Create: `resources/js/api.js` (Axios instance with auth)

- [ ] **Step 1: Create DevLoginController**

Create `app/Http/Controllers/Auth/DevLoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DevLoginController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (app()->environment('production')) {
            abort(403, 'Dev login is not available in production.');
        }

        $user = User::where('email', 'dev@draplo.test')->firstOrFail();

        $token = $user->createToken('dev-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
}
```

- [ ] **Step 2: Add dev login route to routes/web.php**

Add before the SPA catch-all:

```php
use App\Http\Controllers\Auth\DevLoginController;

Route::get('/dev/login', DevLoginController::class);
```

- [ ] **Step 3: Create DevUserSeeder**

Create `database/seeders/DevUserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'dev@draplo.test'],
            [
                'name' => 'Dev User',
                'github_id' => 'dev-12345',
                'github_username' => 'devuser',
                'plan' => 'free',
                'generation_count' => 0,
            ]
        );
    }
}
```

- [ ] **Step 4: Create Booking Platform template.json**

Create `storage/app/templates/booking-platform/template.json`:

```json
{
    "name": "Booking & Reservation Platform",
    "slug": "booking-platform",
    "category": "operations",
    "description": "Appointment scheduling with calendar, reminders, and online booking. Perfect for salons, clinics, studios, sports courts.",
    "icon": "calendar_month",
    "industries": ["healthcare", "beauty", "fitness", "education", "services"],
    "complexity": "medium",
    "models_count": 8,
    "tags": ["Scheduling", "Services"],
    "includes": ["multi-tenancy", "calendar", "sms-reminders", "public-booking-page", "stripe"],
    "available": true
}
```

- [ ] **Step 5: Create Booking Platform wizard-defaults.json**

Create `storage/app/templates/booking-platform/wizard-defaults.json`:

```json
{
    "step_describe": {
        "name": "",
        "description": "Online booking and appointment management platform",
        "problem": "Manual scheduling via phone/Viber leads to double bookings, no-shows, and wasted time"
    },
    "step_users": {
        "app_type": "b2b_saas",
        "roles": [
            {"name": "admin", "description": "Business owner, manages everything", "removable": false, "renameable": false},
            {"name": "provider", "description": "Service provider (e.g., hairdresser, dentist, trainer)", "removable": true, "renameable": true},
            {"name": "client", "description": "Books appointments, receives reminders", "removable": false, "renameable": false}
        ]
    },
    "step_models": {
        "models": [
            {"name": "Tenant", "locked": true, "description": "Business/venue", "fields": [
                {"name": "name", "type": "string"},
                {"name": "address", "type": "string"},
                {"name": "phone", "type": "string"},
                {"name": "logo", "type": "string"},
                {"name": "timezone", "type": "string"},
                {"name": "working_hours", "type": "json"}
            ]},
            {"name": "Service", "locked": false, "description": "Offered service", "fields": [
                {"name": "name", "type": "string"},
                {"name": "duration_minutes", "type": "integer"},
                {"name": "price", "type": "decimal"},
                {"name": "description", "type": "text"},
                {"name": "active", "type": "boolean"}
            ]},
            {"name": "Provider", "locked": false, "description": "Person who delivers the service", "fields": [
                {"name": "name", "type": "string"},
                {"name": "email", "type": "string"},
                {"name": "phone", "type": "string"},
                {"name": "specialization", "type": "string"},
                {"name": "working_hours", "type": "json"},
                {"name": "active", "type": "boolean"}
            ]},
            {"name": "Appointment", "locked": false, "description": "Scheduled booking", "fields": [
                {"name": "client_id", "type": "foreignId"},
                {"name": "provider_id", "type": "foreignId"},
                {"name": "service_id", "type": "foreignId"},
                {"name": "starts_at", "type": "timestamp"},
                {"name": "ends_at", "type": "timestamp"},
                {"name": "status", "type": "string"},
                {"name": "notes", "type": "text"}
            ]},
            {"name": "Client", "locked": false, "description": "Customer who books", "fields": [
                {"name": "name", "type": "string"},
                {"name": "email", "type": "string"},
                {"name": "phone", "type": "string"},
                {"name": "notes", "type": "text"}
            ]},
            {"name": "WorkingHours", "locked": false, "description": "Provider availability per day", "fields": [
                {"name": "provider_id", "type": "foreignId"},
                {"name": "day_of_week", "type": "integer"},
                {"name": "start_time", "type": "string"},
                {"name": "end_time", "type": "string"}
            ]},
            {"name": "BlockedSlot", "locked": false, "description": "Time off / unavailable period", "fields": [
                {"name": "provider_id", "type": "foreignId"},
                {"name": "starts_at", "type": "timestamp"},
                {"name": "ends_at", "type": "timestamp"},
                {"name": "reason", "type": "string"}
            ]},
            {"name": "Reminder", "locked": false, "description": "SMS/email reminder", "fields": [
                {"name": "appointment_id", "type": "foreignId"},
                {"name": "type", "type": "string"},
                {"name": "scheduled_at", "type": "timestamp"},
                {"name": "sent_at", "type": "timestamp"},
                {"name": "status", "type": "string"}
            ]}
        ]
    },
    "step_auth": {
        "multi_tenant": true,
        "auth_method": "sanctum",
        "guest_access": true,
        "guest_description": "Public booking page where clients book without registration"
    },
    "step_integrations": {
        "selected": ["sms", "stripe", "email"],
        "notes": "SMS for appointment reminders, Stripe for online payment/deposit, email for confirmations"
    }
}
```

- [ ] **Step 6: Create metadata-only template.json files for remaining 24 templates**

Create a seeder or script that writes `template.json` (metadata only, no `wizard-defaults.json`) to `storage/app/templates/{slug}/` for the remaining 24 templates. Each has `"available": false`. Use the data from `.claude-reference/features/template-library.md`.

Example for CRM (`storage/app/templates/crm/template.json`):
```json
{
    "name": "CRM",
    "slug": "crm",
    "category": "sales",
    "description": "Customer relationship management with pipeline tracking, interaction history, and lead scoring.",
    "icon": "bolt",
    "industries": ["sales", "consulting", "real-estate", "insurance", "recruitment"],
    "complexity": "medium-high",
    "models_count": 10,
    "tags": ["Pipeline", "SaaS"],
    "includes": ["multi-tenancy", "pipeline", "email", "custom-fields"],
    "available": false
}
```

Repeat for all 24 remaining templates with appropriate metadata, icons, and tags. The `TemplateSeeder` should create these directories and files.

- [ ] **Step 7: Create TemplateSeeder**

Create `database/seeders/TemplateSeeder.php`. This seeder writes all 25 template directories with their `template.json` files. The Booking Platform already has `wizard-defaults.json` from steps 4-5.

**Data source:** Pull template metadata from `.claude-reference/features/template-library.md` — the 25 templates are listed there with name, slug (derive from name), category, description, core models (count them for `models_count`), complexity, and key features (use as tags).

**Icon mapping by category:**
- operations: `calendar_month`, `task_alt`, `inventory_2`, `engineering`, `local_shipping`
- sales: `bolt`, `receipt_long`, `shopping_cart`, `card_membership`
- content: `article`, `mail`, `support_agent`
- marketplace: `storefront`, `work`, `place`
- education: `school`, `menu_book`
- health: `health_and_safety`, `fitness_center`
- hospitality: `restaurant`, `home_work`
- analytics: `bar_chart`, `monitor_heart`
- specialized: `event`, `terminal`

The seeder should:
1. Ensure `storage/app/templates/` directory exists
2. For each of the 25 templates, create `storage/app/templates/{slug}/template.json`
3. Only Booking Platform gets `"available": true` — all others `"available": false`
4. Skip writing files that already exist (idempotent)

- [ ] **Step 8: Update DatabaseSeeder**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DevUserSeeder::class,
            TemplateSeeder::class,
        ]);
    }
}
```

- [ ] **Step 9: Run seeders and verify**

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\User::count();"
# Expected: 1
ls storage/app/templates/
# Expected: 25 directories
```

- [ ] **Step 10: Create resources/js/api.js**

```js
import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Add auth token to all requests
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Auto dev-login if no token (local dev only)
let loginPromise = null;

export async function ensureAuth() {
    const token = localStorage.getItem('auth_token');
    if (token) return token;

    if (!loginPromise) {
        loginPromise = fetch('/dev/login')
            .then(res => res.json())
            .then(data => {
                localStorage.setItem('auth_token', data.token);
                return data.token;
            });
    }

    return loginPromise;
}

export default api;
```

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: add dev-mode auth, seeders, Booking Platform template data, Axios API client"
```

---

## Task 5: TemplateService + API Controllers + Routes

**Files:**
- Create: `app/Services/TemplateService.php`
- Create: `app/Http/Controllers/TemplateController.php`
- Create: `app/Http/Controllers/Wizard/WizardController.php`
- Create: `app/Http/Controllers/ProjectController.php`
- Create: `app/Http/Requests/CreateProjectRequest.php`
- Create: `app/Http/Requests/SaveWizardStepRequest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create TemplateService**

Create `app/Services/TemplateService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class TemplateService
{
    public function listTemplates(): array
    {
        $templatesPath = storage_path('app/templates');

        if (!is_dir($templatesPath)) {
            return [];
        }

        $templates = [];
        $dirs = array_filter(glob($templatesPath . '/*'), 'is_dir');

        foreach ($dirs as $dir) {
            $metaFile = $dir . '/template.json';
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true);
                if ($meta) {
                    $templates[] = $meta;
                }
            }
        }

        usort($templates, fn($a, $b) => ($b['available'] ?? false) <=> ($a['available'] ?? false));

        return $templates;
    }

    public function getDefaults(string $slug): ?array
    {
        $defaultsFile = storage_path("app/templates/{$slug}/wizard-defaults.json");

        if (!file_exists($defaultsFile)) {
            return null;
        }

        return json_decode(file_get_contents($defaultsFile), true);
    }

    public function getTemplate(string $slug): ?array
    {
        $metaFile = storage_path("app/templates/{$slug}/template.json");

        if (!file_exists($metaFile)) {
            return null;
        }

        return json_decode(file_get_contents($metaFile), true);
    }
}
```

- [ ] **Step 2: Create TemplateController**

Create `app/Http/Controllers/TemplateController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\TemplateService;
use Illuminate\Http\JsonResponse;

class TemplateController extends Controller
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->templateService->listTemplates());
    }
}
```

- [ ] **Step 3: Create CreateProjectRequest**

Create `app/Http/Requests/CreateProjectRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_slug' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

- [ ] **Step 4: Create SaveWizardStepRequest**

Create `app/Http/Requests/SaveWizardStepRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWizardStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'step' => ['required', 'string', 'in:describe,users,models,auth,integrations,review'],
            'data' => ['required', 'array'],
        ];

        // Per-step validation
        if ($this->input('step') === 'describe') {
            $rules['data.name'] = ['required', 'string', 'max:255'];
        }

        if ($this->input('step') === 'models') {
            $rules['data.models'] = ['required', 'array', 'min:1'];
            $rules['data.models.*.name'] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }
}
```

- [ ] **Step 5: Create WizardController**

Create `app/Http/Controllers/Wizard/WizardController.php`:

```php
<?php

namespace App\Http\Controllers\Wizard;

use App\Enums\ProjectStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProjectRequest;
use App\Http\Requests\SaveWizardStepRequest;
use App\Models\Project;
use App\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class WizardController extends Controller
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    private static function emptyDefaults(): array
    {
        return [
            'step_describe' => ['name' => '', 'description' => '', 'problem' => ''],
            'step_users' => ['app_type' => null, 'roles' => []],
            'step_models' => ['models' => []],
            'step_auth' => ['multi_tenant' => false, 'auth_method' => 'sanctum', 'guest_access' => false, 'guest_description' => ''],
            'step_integrations' => ['selected' => [], 'notes' => ''],
        ];
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        $templateSlug = $request->input('template_slug');

        if ($templateSlug) {
            $wizardData = $this->templateService->getDefaults($templateSlug) ?? self::emptyDefaults();
        } else {
            $wizardData = self::emptyDefaults();
        }

        $project = $request->user()->projects()->create([
            'name' => $wizardData['step_describe']['name'] ?? 'Untitled Project',
            'slug' => Str::slug($wizardData['step_describe']['name'] ?? 'project-' . Str::random(6)),
            'template_slug' => $templateSlug,
            'description' => $wizardData['step_describe']['description'] ?? null,
            'wizard_data' => $wizardData,
            'status' => ProjectStatusEnum::Draft,
        ]);

        return response()->json($project, 201);
    }

    public function show(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        return response()->json($project);
    }

    public function update(SaveWizardStepRequest $request, Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $step = $request->input('step');
        $data = $request->input('data');

        $wizardData = $project->wizard_data ?? [];
        $wizardData["step_{$step}"] = $data;
        $project->wizard_data = $wizardData;

        // Update project name/description from describe step
        if ($step === 'describe') {
            $project->name = $data['name'] ?? $project->name;
            $project->slug = Str::slug($data['name'] ?? $project->slug);
            $project->description = $data['description'] ?? $project->description;
        }

        // Mark wizard as complete on review step
        if ($step === 'review') {
            $project->status = ProjectStatusEnum::WizardDone;
        }

        $project->save();

        return response()->json($project);
    }
}
```

- [ ] **Step 6: Create ProjectController**

Create `app/Http/Controllers/ProjectController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = auth()->user()->projects()
            ->select('id', 'name', 'slug', 'template_slug', 'status', 'updated_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($projects);
    }

    public function destroy(Project $project): Response
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $project->delete();

        return response()->noContent();
    }
}
```

- [ ] **Step 7: Configure routes/api.php**

```php
<?php

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

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
});
```

- [ ] **Step 8: Configure CORS for Vite dev server**

Laravel 12 handles CORS via the `HandleCors` middleware. Check if `config/cors.php` exists. If it does, update `allowed_origins`. If it doesn't (Laravel 11+ moved CORS to `bootstrap/app.php`), configure it there.

**If `config/cors.php` exists:**
```php
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
```

**If it does NOT exist**, publish it:
```bash
php artisan config:publish cors
```
Then update `allowed_origins` as above.

**Alternative approach (simpler for dev):** Since Vite can proxy API requests, add to `vite.config.js` inside `defineConfig`:
```js
server: {
    proxy: {
        '/api': 'http://localhost:8000',
        '/dev': 'http://localhost:8000',
        '/sanctum': 'http://localhost:8000',
    },
},
```
This avoids CORS entirely in development. The React SPA calls `/api/...` which Vite proxies to Laravel. **This is the recommended approach for Phase 1.**

- [ ] **Step 9: Verify API endpoints**

```bash
# Start services
docker-compose up -d
php artisan migrate:fresh --seed
php artisan serve &

# Get dev token
TOKEN=$(curl -s http://localhost:8000/dev/login | php -r "echo json_decode(file_get_contents('php://stdin'))->token;")

# Test templates endpoint (public)
curl -s http://localhost:8000/api/templates | php -r "echo count(json_decode(file_get_contents('php://stdin'))) . ' templates';"
# Expected: 25 templates

# Test create project
curl -s -X POST http://localhost:8000/api/wizard/projects \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"template_slug": "booking-platform"}' | php -r "echo json_decode(file_get_contents('php://stdin'))->name;"
# Expected: Untitled Project (name empty in defaults)

# Test update wizard step
curl -s -X PUT http://localhost:8000/api/wizard/projects/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"step": "describe", "data": {"name": "DentBook", "description": "Dental booking", "problem": "Double bookings"}}' | php -r "echo json_decode(file_get_contents('php://stdin'))->name;"
# Expected: DentBook
```

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: add TemplateService, wizard API controllers, project endpoints, routes"
```

---

## Task 6: React AppLayout (Sidebar + TopBar)

**Files:**
- Create: `resources/js/components/AppLayout.jsx`
- Create: `resources/js/components/Sidebar.jsx`
- Create: `resources/js/components/TopBar.jsx`
- Modify: `resources/js/app.jsx` (wrap routes in AppLayout)

- [ ] **Step 1: Create Sidebar component**

Create `resources/js/components/Sidebar.jsx`:

Implements the sidebar from the template_library mockup:
- Draplo logo at top
- Nav items: Dashboard, Library, Wizard, Deployments, Settings
- Active state: primary background tint + right border
- Material Symbols icons for each nav item
- "New Project" button at bottom
- User info at very bottom

Use the canonical design tokens: `bg-background` for sidebar bg, `text-on-surface-variant` for inactive items, `text-primary` for active, `bg-primary/10` for active background.

The sidebar receives `activePage` prop to highlight the current nav item, and an optional `wizardProgress` prop `{ step, totalSteps, templateName }` to show the progress widget when inside the wizard.

- [ ] **Step 2: Create TopBar component**

Create `resources/js/components/TopBar.jsx`:

Fixed top bar with:
- Left: nav links (Templates, Pricing, Docs, OS)
- Right: Sign In button (text), Deploy Now button (primary gradient)
- Background: `bg-background/80 backdrop-blur-xl`
- Bottom border: `border-primary/15` (ghost border per design system)

- [ ] **Step 3: Create AppLayout component**

Create `resources/js/components/AppLayout.jsx`:

```jsx
import Sidebar from './Sidebar';
import TopBar from './TopBar';

export default function AppLayout({ children, activePage, wizardProgress }) {
    return (
        <>
            <Sidebar activePage={activePage} wizardProgress={wizardProgress} />
            <TopBar />
            <main className="ml-64 pt-16 min-h-screen bg-background">
                {children}
            </main>
        </>
    );
}
```

- [ ] **Step 4: Update app.jsx to use AppLayout with routing**

```jsx
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ensureAuth } from './api';
import { useEffect, useState } from 'react';
import AppLayout from './components/AppLayout';

// Lazy placeholder pages
function TemplateLibrary() {
    return <div className="p-12"><h1 className="text-4xl font-extrabold text-white tracking-tight">Template Library</h1></div>;
}

function ProjectList() {
    return <div className="p-12"><h1 className="text-4xl font-extrabold text-white tracking-tight">Projects</h1></div>;
}

function App() {
    const [ready, setReady] = useState(false);

    useEffect(() => {
        ensureAuth().then(() => setReady(true));
    }, []);

    if (!ready) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-background">
                <div className="text-primary text-lg font-mono">Initializing...</div>
            </div>
        );
    }

    return (
        <Routes>
            <Route path="/templates" element={
                <AppLayout activePage="library">
                    <TemplateLibrary />
                </AppLayout>
            } />
            <Route path="/projects" element={
                <AppLayout activePage="dashboard">
                    <ProjectList />
                </AppLayout>
            } />
            <Route path="*" element={<Navigate to="/templates" replace />} />
        </Routes>
    );
}

const root = createRoot(document.getElementById('app'));
root.render(
    <BrowserRouter>
        <App />
    </BrowserRouter>
);
```

- [ ] **Step 5: Verify layout renders in browser**

```bash
npm run dev &
php artisan serve &
# Visit http://localhost:8000/templates
# Should see sidebar + top bar + "Template Library" heading
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add AppLayout with Sidebar and TopBar following design system"
```

---

## Task 7: Base UI Components

**Files:**
- Create: `resources/js/components/Button.jsx`
- Create: `resources/js/components/Input.jsx`
- Create: `resources/js/components/Textarea.jsx`
- Create: `resources/js/components/Card.jsx`
- Create: `resources/js/components/Chip.jsx`
- Create: `resources/js/components/Toggle.jsx`
- Create: `resources/js/components/Toast.jsx`
- Create: `resources/js/components/CategoryFilter.jsx`

- [ ] **Step 1: Create Button component**

Three variants matching DESIGN.md:
- **primary**: `bg-gradient-to-r from-primary to-primary-container text-on-primary font-bold rounded-md`
- **secondary**: `bg-surface-container-highest` with ghost border (1px outline-variant at 15% opacity)
- **tertiary**: no background, `text-primary`

Props: `variant`, `children`, `disabled`, `loading`, `onClick`, `className`, `type`

When `loading` is true, show a small spinner and disable the button.

- [ ] **Step 2: Create Input component**

Matching DESIGN.md input spec:
- Background: `bg-surface-container-lowest`
- Border: 1px ghost border (`border-outline-variant/15`)
- Focus: border transitions to `border-primary/50` with `ring-2 ring-primary-container/5`
- Font: `font-mono` for input text
- Props: `label`, `value`, `onChange`, `placeholder`, `required`, `error`

- [ ] **Step 3: Create Textarea component**

Same styling as Input but multi-line. Props: `label`, `value`, `onChange`, `placeholder`, `rows`, `required`, `error`

- [ ] **Step 4: Create Card component**

- Background: `bg-surface-container`
- Hover: `hover:bg-surface-container-high`
- Border: `border border-outline-variant/5`
- Rounded: `rounded-xl`
- Transition: `transition-all duration-300`
- Props: `children`, `className`, `onClick`

- [ ] **Step 5: Create Chip component**

- Background: `bg-background`
- Text: `text-on-surface-variant text-[10px] font-mono`
- Rounded: `rounded`
- Optional status dot (colored circle before text)
- Props: `label`, `dotColor`, `className`

- [ ] **Step 6: Create Toggle component**

- Switch-style toggle
- Off: `bg-surface-container-highest`
- On: `bg-primary-container`
- Thumb: white circle
- Props: `checked`, `onChange`, `label`, `description`

- [ ] **Step 7: Create Toast component**

- Fixed position bottom-right
- Background: `bg-surface-container-high` with ghost border
- Error variant: `border-tertiary-container/30`
- Auto-dismiss after 5 seconds
- Props: `message`, `type` (error/success), `onDismiss`

Use a simple global state pattern (module-level variable + event) or just render it from the parent.

- [ ] **Step 8: Create CategoryFilter component**

- Horizontal scrollable row of pill buttons
- Active: `bg-primary-container text-on-primary-container`
- Inactive: `bg-surface-container-highest text-on-surface-variant border border-outline-variant/10`
- Scrollable: `overflow-x-auto` with hidden scrollbar
- Props: `categories`, `active`, `onChange`

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add base UI components (Button, Input, Card, Chip, Toggle, Toast, CategoryFilter)"
```

---

## Task 8: WizardLayout + useProject Hook

**Files:**
- Create: `resources/js/hooks/useProject.js`
- Create: `resources/js/pages/Wizard/WizardLayout.jsx`
- Modify: `resources/js/app.jsx` (add wizard route)

- [ ] **Step 1: Create useProject hook**

Create `resources/js/hooks/useProject.js`:

```js
import { useState, useEffect, useCallback } from 'react';
import api from '../api';

const STEPS = ['describe', 'users', 'models', 'auth', 'integrations', 'review'];

export default function useProject(projectId) {
    const [project, setProject] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await api.get(`/wizard/projects/${projectId}`);
            setProject(data);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to load project');
        } finally {
            setLoading(false);
        }
    }, [projectId]);

    useEffect(() => { load(); }, [load]);

    const saveStep = useCallback(async (step, data) => {
        setSaving(true);
        setError(null);
        try {
            const { data: updated } = await api.put(`/wizard/projects/${projectId}`, { step, data });
            setProject(updated);
            return updated;
        } catch (err) {
            const msg = err.response?.data?.message || 'Failed to save';
            setError(msg);
            throw err;
        } finally {
            setSaving(false);
        }
    }, [projectId]);

    const getStepData = useCallback((step) => {
        return project?.wizard_data?.[`step_${step}`] || {};
    }, [project]);

    return { project, loading, saving, error, saveStep, getStepData, reload: load, STEPS };
}
```

- [ ] **Step 2: Create WizardLayout**

Create `resources/js/pages/Wizard/WizardLayout.jsx`:

This component:
- Takes `projectId` from `useParams()`
- Uses `useProject(projectId)` to load project data
- Renders AppLayout with `activePage="wizard"` and `wizardProgress` data
- Shows current step component based on URL or internal state
- Bottom bar: Back button (previous step), Save Draft, Next button (validates + saves + advances)
- Step components receive `stepData`, `onSave`, `saving` props
- Navigation: tracks current step index (0-5), Next calls `saveStep(stepName, localData)` then increments

- [ ] **Step 3: Add wizard route to app.jsx**

Add to the Routes:
```jsx
<Route path="/wizard/:projectId" element={<WizardLayout />} />
```

Import WizardLayout from `./pages/Wizard/WizardLayout`.

- [ ] **Step 4: Verify wizard shell loads**

```bash
# Create a test project via API
TOKEN=$(curl -s http://localhost:8000/dev/login | php -r "echo json_decode(file_get_contents('php://stdin'))->token;")
PROJECT_ID=$(curl -s -X POST http://localhost:8000/api/wizard/projects \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"template_slug": "booking-platform"}' | php -r "echo json_decode(file_get_contents('php://stdin'))->id;")
echo "Visit: http://localhost:8000/wizard/$PROJECT_ID"
```

Expected: Wizard layout with sidebar showing progress, bottom nav bar, and placeholder step content.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add WizardLayout with useProject hook, step navigation, bottom bar"
```

---

## Task 9: Wizard Step 1 — Describe Your App

**Files:**
- Create: `resources/js/pages/Wizard/StepDescribe.jsx`

- [ ] **Step 1: Create StepDescribe component**

The component receives `stepData` (from `wizard_data.step_describe`) and `onSave` callback.

Layout:
- Header: eyebrow text ("Template: {name} → Step 1 of 6"), heading with Material Symbol icon (`edit_note`)
- Three fields using the `Input` and `Textarea` components:
  - `name` (Input, required) — "What's your app called?"
  - `description` (Textarea, 3 rows) — "Describe your app in a sentence"
  - `problem` (Textarea, 3 rows) — "What problem does this solve?"

Uses local state initialized from `stepData`. The `onSave` callback is called by WizardLayout when user clicks "Next".

- [ ] **Step 2: Wire into WizardLayout**

Import StepDescribe and render it when current step is `describe` (index 0).

- [ ] **Step 3: Test manually in browser**

1. Create a project from Booking Platform template
2. Navigate to `/wizard/{id}`
3. Should see Step 1 pre-populated with template defaults (description + problem filled, name empty)
4. Fill in name, click Next
5. Verify: API call to `PUT /api/wizard/projects/{id}` with `step: "describe"`
6. Step advances to Step 2

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add wizard Step 1 - Describe Your App"
```

---

## Task 10: Wizard Step 2 — Who Uses It?

**Files:**
- Create: `resources/js/pages/Wizard/StepUsers.jsx`

- [ ] **Step 1: Create StepUsers component**

Layout:
- Header with Material Symbol icon (`group`)
- App type selector: row of pill buttons (B2B SaaS, B2C App, Marketplace, Internal Tool, API Service)
- Roles section:
  - Each role as a Card with:
    - Name field (Input, editable if `renameable`)
    - Description field (Input)
    - Delete button (if `removable`) — Material Symbol `delete`
    - Non-removable roles show a subtle lock indicator
  - "Add Role" button at bottom (dashed border card with + icon)

Local state: `appType` and `roles` array. Initialized from `stepData.app_type` and `stepData.roles`.

Adding a role: pushes `{ name: '', description: '', removable: true, renameable: true }`.
Removing: filters by index. Renaming: updates name at index.

- [ ] **Step 2: Wire into WizardLayout**

Render StepUsers when current step is `users` (index 1).

- [ ] **Step 3: Test manually**

1. Verify roles pre-populated from Booking Platform (admin, provider, client)
2. Verify admin is not removable
3. Verify provider is renameable
4. Add a new role, rename it, remove it
5. Click Next, verify API save

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add wizard Step 2 - Who Uses It (roles + app type)"
```

---

## Task 11: Wizard Step 3 — Core Models

**Files:**
- Create: `resources/js/pages/Wizard/StepModels.jsx`

- [ ] **Step 1: Create StepModels component**

This is the most complex step. Layout matches the `wizard_core_models` mockup (using canonical DESIGN.md tokens, Berkeley Mono font, Material Symbol `schema` icon instead of emoji):

- Header with Material Symbol icon (`schema`)
- List of model cards, each containing:
  - Drag handle icon (visual only, `drag_indicator`)
  - Model name (monospace, bold, editable unless locked)
  - Lock badge for locked models (`LOCKED` chip with lock icon)
  - Delete button (if not locked)
  - Fields as chips in a flex wrap:
    - Each field chip: `bg-surface-container-highest border border-outline-variant/5`
    - Type indicator prefixes: `FK` (primary tint bg for foreignId), `T` (indigo for timestamp), `#` (primary for integer), `$` (green for decimal), `?` (yellow for boolean)
    - Regular string fields: no prefix
  - "Add field" button per model (dashed border chip with + icon)
- "Add New Core Model" button at bottom (full-width dashed border card)
- Floating relationships widget (fixed right, desktop only):
  - Auto-detects `belongsTo` from `_id` suffix fields
  - Shows relationship list grouped by model

Local state: `models` array from `stepData.models`.

Adding a field: prompts for name and type (dropdown: string, text, integer, decimal, boolean, timestamp, foreignId, json).
Adding a model: pushes `{ name: '', locked: false, description: '', fields: [] }`.

- [ ] **Step 2: Wire into WizardLayout**

Render StepModels when current step is `models` (index 2).

- [ ] **Step 3: Test manually**

1. Verify 8 models pre-populated from Booking Platform
2. Verify Tenant is locked (can't delete, shows LOCKED badge)
3. Verify FK fields (client_id, provider_id, service_id on Appointment) have FK indicator and primary tint
4. Add a new field to Service, add a new model
5. Delete a non-locked model
6. Verify relationships widget updates
7. Click Next, verify API save

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add wizard Step 3 - Core Models with field chips and relationship widget"
```

---

## Task 12: Wizard Step 4 — Auth & Tenancy

**Files:**
- Create: `resources/js/pages/Wizard/StepAuth.jsx`

- [ ] **Step 1: Create StepAuth component**

Layout:
- Header with Material Symbol icon (`shield`)
- Three sections as Cards:
  1. **Multi-tenancy**: Toggle + description text explaining what it means
  2. **Auth method**: Currently just shows "Laravel Sanctum" as the selected method (no selector needed since it's the only option)
  3. **Guest access**: Toggle + conditional Textarea for `guest_description` (shown when toggle is on)

Local state from `stepData`: `multi_tenant`, `auth_method`, `guest_access`, `guest_description`.

- [ ] **Step 2: Wire into WizardLayout**

Render StepAuth when current step is `auth` (index 3).

- [ ] **Step 3: Test manually**

1. Verify pre-populated from Booking Platform (multi_tenant: true, guest_access: true with description)
2. Toggle multi-tenancy off and on
3. Toggle guest access off — description field hides
4. Click Next, verify save

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add wizard Step 4 - Auth & Tenancy"
```

---

## Task 13: Wizard Step 5 — Integrations

**Files:**
- Create: `resources/js/pages/Wizard/StepIntegrations.jsx`

- [ ] **Step 1: Create StepIntegrations component**

Layout:
- Header with Material Symbol icon (`extension`)
- Grid of integration cards (2 columns on desktop, 1 on mobile)
- Each card:
  - Material Symbol icon (per integration)
  - Integration name (bold)
  - Description of what it generates
  - Toggle switch on the right
- Integration data:
  - `stripe` — icon: `payments`, generates: "Cashier config, webhook"
  - `sms` — icon: `sms`, generates: "Notification channel"
  - `email` — icon: `email`, generates: "Mail config, templates"
  - `file_storage` — icon: `cloud_upload`, generates: "Filesystem config"
  - `ai` — icon: `smart_toy`, generates: "Anthropic/OpenAI setup"
  - `search` — icon: `search`, generates: "Scout + Meilisearch"
  - `websockets` — icon: `sync_alt`, generates: "Reverb config"
- Notes textarea at bottom

Local state: `selected` array of integration keys, `notes` string.

- [ ] **Step 2: Wire into WizardLayout**

Render StepIntegrations when current step is `integrations` (index 4).

- [ ] **Step 3: Test manually**

1. Verify pre-checked from Booking Platform (sms, stripe, email)
2. Toggle some integrations on/off
3. Add notes
4. Click Next, verify save

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add wizard Step 5 - Integrations"
```

---

## Task 14: Wizard Step 6 — Review & Generate

**Files:**
- Create: `resources/js/pages/Wizard/StepReview.jsx`

- [ ] **Step 1: Create StepReview component**

Layout:
- Header with Material Symbol icon (`checklist`)
- Expandable/collapsible sections for each previous step:
  - **App Description**: name, description, problem
  - **Users & Roles**: app type, list of roles
  - **Core Models**: model names with field counts
  - **Auth & Tenancy**: multi-tenant, auth method, guest access
  - **Integrations**: selected integrations, notes
- Each section has an "Edit" link (Material Symbol `edit`) that navigates back to that step
- Bottom: "Generate" button (disabled, primary gradient but with opacity). Tooltip on hover: "Coming in Phase 2"
- Saving this step sets `status: wizard_done`

Each section uses a Card component. Collapsed shows just the section title + summary. Expanded shows full details.

- [ ] **Step 2: Wire into WizardLayout**

Render StepReview when current step is `review` (index 5).

The bottom nav "Next" button on this step is replaced by "Complete Wizard" which calls `saveStep('review', {})` and shows a success message.

- [ ] **Step 3: Test manually**

1. Complete all previous steps
2. Verify Review shows correct summary of all data
3. Click "Edit" on a section — navigates back to that step
4. Return to Review — data is preserved
5. Click "Complete Wizard" — project status becomes `wizard_done`

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add wizard Step 6 - Review & Generate (summary view)"
```

---

## Task 15: Template Library Page

**Files:**
- Create: `resources/js/pages/TemplateLibrary.jsx`
- Modify: `resources/js/app.jsx` (replace placeholder)

- [ ] **Step 1: Create TemplateLibrary page**

Layout matches the `template_library` mockup exactly:

- Header section:
  - Eyebrow: `text-primary font-mono text-xs tracking-widest uppercase` — "System / Library / v4.0"
  - Title: `text-5xl font-extrabold tracking-tight text-white` — "Template Library"
  - Subtitle: `text-on-surface-variant text-lg` — "25 industry-specific scaffolds. Pick one, customize it, ship it."

- CategoryFilter component with categories: All, Operations, Sales, Content, Platform, Education, Health, Hospitality, Analytics, Specialized

- Template grid (3 cols desktop, 2 tablet, 1 mobile):
  - First card: "Start from Scratch" — dashed border, centered icon (`auto_awesome` filled) + text. Clicking creates project with no template.
  - Template cards: loaded from `GET /api/templates`
    - Each card renders: icon, category badge, name, description, tag chips, footer with model count + complexity + arrow
    - Clicking an available template: `POST /api/wizard/projects` → redirect to `/wizard/{id}`
    - Clicking an unavailable template: show Toast "Coming soon"

- Uses `useState` for active category, `useEffect` to fetch templates from API.

- [ ] **Step 2: Replace placeholder in app.jsx**

Import the real TemplateLibrary component and replace the inline placeholder.

- [ ] **Step 3: Test manually**

1. Visit `/templates`
2. Should see 25 template cards + "Start from Scratch"
3. Category filter works (client-side filtering)
4. Click "Booking Platform" — creates project and redirects to wizard
5. Click any unavailable template — shows "Coming soon" toast
6. Click "Start from Scratch" — creates project with empty defaults, redirects to wizard

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add Template Library page with category filter and template grid"
```

---

## Task 16: Project List Page

**Files:**
- Create: `resources/js/pages/ProjectList.jsx`
- Modify: `resources/js/app.jsx` (replace placeholder)
- Create: `resources/js/pages/NotFound.jsx`

- [ ] **Step 1: Create ProjectList page**

Simple page showing user's projects:
- Header: "Your Projects"
- List of project cards (or empty state with link to Template Library)
- Each card: project name (bold), template name, status chip, last updated (monospace), "Resume Wizard" link (if draft), delete button
- Delete: calls `DELETE /api/projects/{id}` with confirmation

- [ ] **Step 2: Create NotFound page**

Simple 404 page: "Project not found" heading + link back to `/projects`.

- [ ] **Step 3: Wire into app.jsx**

Import ProjectList, NotFound. Add NotFound route for non-matching paths that aren't SPA routes.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add ProjectList page and 404 NotFound page"
```

---

## Task 17: Backend Tests

**Files:**
- Create: `tests/Feature/AuthTest.php`
- Create: `tests/Feature/WizardTest.php`
- Create: `tests/Feature/TemplateTest.php`
- Create: `tests/Unit/TemplateServiceTest.php`

- [ ] **Step 0: Configure Pest test setup**

Ensure `tests/Pest.php` includes RefreshDatabase for feature tests:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->in('Feature');
```

This is REQUIRED — without it, database state leaks between tests and everything fails.

- [ ] **Step 1: Write AuthTest**

Create `tests/Feature/AuthTest.php`:

```php
<?php

use App\Models\User;

it('returns a Sanctum token via dev login', function () {
    User::factory()->create(['email' => 'dev@draplo.test']);

    $response = $this->get('/dev/login');

    $response->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

it('blocks dev login in production', function () {
    User::factory()->create(['email' => 'dev@draplo.test']);

    app()->detectEnvironment(fn () => 'production');

    $response = $this->get('/dev/login');

    $response->assertForbidden();
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});
```

- [ ] **Step 2: Write TemplateTest**

Create `tests/Feature/TemplateTest.php`:

```php
<?php

it('returns a list of templates', function () {
    // Seed templates
    $this->artisan('db:seed', ['--class' => 'TemplateSeeder']);

    $response = $this->getJson('/api/templates');

    $response->assertOk()
        ->assertJsonCount(25);

    // Booking Platform should be available
    $bookingPlatform = collect($response->json())->firstWhere('slug', 'booking-platform');
    expect($bookingPlatform)->not->toBeNull();
    expect($bookingPlatform['available'])->toBeTrue();
});
```

- [ ] **Step 3: Write WizardTest**

Create `tests/Feature/WizardTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Project;
use App\Enums\ProjectStatusEnum;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'TemplateSeeder']);
    $this->user = User::factory()->create();
});

it('creates a project from a template', function () {
    $this->actingAs($this->user)
        ->postJson('/api/wizard/projects', ['template_slug' => 'booking-platform'])
        ->assertCreated()
        ->assertJsonPath('template_slug', 'booking-platform')
        ->assertJsonPath('status', 'draft');
});

it('creates a project without a template', function () {
    $this->actingAs($this->user)
        ->postJson('/api/wizard/projects', [])
        ->assertCreated()
        ->assertJsonPath('template_slug', null);
});

it('loads a project with wizard data', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->getJson("/api/wizard/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('id', $project->id);
});

it('prevents accessing another user\'s project', function () {
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($this->user)
        ->getJson("/api/wizard/projects/{$project->id}")
        ->assertForbidden();
});

it('saves wizard step data', function () {
    $project = $this->actingAs($this->user)
        ->postJson('/api/wizard/projects', ['template_slug' => 'booking-platform'])
        ->json();

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project['id']}", [
            'step' => 'describe',
            'data' => ['name' => 'DentBook', 'description' => 'Dental booking', 'problem' => 'Double bookings'],
        ])
        ->assertOk()
        ->assertJsonPath('name', 'DentBook')
        ->assertJsonPath('wizard_data.step_describe.name', 'DentBook');
});

it('validates describe step requires name', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project->id}", [
            'step' => 'describe',
            'data' => ['description' => 'No name provided'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('data.name');
});

it('validates models step requires at least one model', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project->id}", [
            'step' => 'models',
            'data' => ['models' => []],
        ])
        ->assertUnprocessable();
});

it('sets status to wizard_done on review step', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project->id}", [
            'step' => 'review',
            'data' => [],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'wizard_done');
});

it('lists user projects', function () {
    Project::factory()->count(3)->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->getJson('/api/projects')
        ->assertOk()
        ->assertJsonCount(3);
});

it('deletes a project', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});

it('prevents deleting another user\'s project', function () {
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertForbidden();
});

it('rejects invalid step names', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project->id}", [
            'step' => 'invalid_step',
            'data' => [],
        ])
        ->assertUnprocessable();
});
```

- [ ] **Step 4: Write TemplateServiceTest**

Create `tests/Unit/TemplateServiceTest.php`:

```php
<?php

use App\Services\TemplateService;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'TemplateSeeder']);
    $this->service = new TemplateService();
});

it('lists all templates', function () {
    $templates = $this->service->listTemplates();

    expect($templates)->toHaveCount(25);
    expect(collect($templates)->firstWhere('slug', 'booking-platform'))->not->toBeNull();
});

it('loads defaults for booking platform', function () {
    $defaults = $this->service->getDefaults('booking-platform');

    expect($defaults)->not->toBeNull();
    expect($defaults)->toHaveKey('step_describe');
    expect($defaults)->toHaveKey('step_models');
    expect($defaults['step_models']['models'])->toHaveCount(8);
});

it('returns null for unknown template slug', function () {
    expect($this->service->getDefaults('nonexistent'))->toBeNull();
});

it('returns template metadata', function () {
    $template = $this->service->getTemplate('booking-platform');

    expect($template['name'])->toBe('Booking & Reservation Platform');
    expect($template['available'])->toBeTrue();
});
```

- [ ] **Step 5: Create model factories needed by tests**

Create `database/factories/ProjectFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'slug' => fake()->slug(3),
            'status' => 'draft',
            'wizard_data' => [],
        ];
    }
}
```

- [ ] **Step 6: Run all tests**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 7: Fix any failing tests**

If tests fail, debug and fix. Common issues:
- Template files not seeded in test environment (ensure TemplateSeeder runs in `beforeEach`)
- Route model binding not matching (check route parameter names)

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add backend tests (auth, wizard, templates) with Pest PHP"
```

---

## Task 18: Final Integration Test + Cleanup

**Files:**
- Modify: various files for polish
- Verify: full end-to-end flow

- [ ] **Step 1: End-to-end manual test**

Full flow:
1. `docker-compose up -d && php artisan migrate:fresh --seed && npm run dev & php artisan serve &`
2. Visit `http://localhost:8000/templates`
3. See 25 templates + "Start from Scratch"
4. Filter by "Operations" — see Booking Platform, Inventory, etc.
5. Click "Booking Platform" — redirected to wizard Step 1
6. Fill in name "DentBook", customize description
7. Click Next through all 6 steps, verifying pre-populated data
8. On Review step, verify all data shows correctly
9. Click "Complete Wizard"
10. Visit `/projects` — see DentBook with status `wizard_done`

- [ ] **Step 2: Run full test suite**

```bash
php artisan test --parallel
```

Expected: All tests green.

- [ ] **Step 3: Update .env.example with complete docs**

Ensure all env vars are documented with comments.

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore: Phase 1 complete — foundation, wizard UI, template library, API, tests"
```

---

## Summary

| Task | Description | Estimated Steps |
|------|-------------|-----------------|
| 1 | Laravel 12 scaffold + Docker Compose | 6 |
| 2 | Vite + React 18 + Tailwind CSS 4 | 9 |
| 3 | Database migrations, enums, models | 10 |
| 4 | Dev-mode auth, seeders, template data | 11 |
| 5 | TemplateService + API controllers + routes | 10 |
| 6 | React AppLayout (Sidebar + TopBar) | 6 |
| 7 | Base UI components | 9 |
| 8 | WizardLayout + useProject hook | 5 |
| 9 | Wizard Step 1 — Describe | 4 |
| 10 | Wizard Step 2 — Users | 4 |
| 11 | Wizard Step 3 — Models | 4 |
| 12 | Wizard Step 4 — Auth | 4 |
| 13 | Wizard Step 5 — Integrations | 4 |
| 14 | Wizard Step 6 — Review | 4 |
| 15 | Template Library page | 4 |
| 16 | Project List + 404 page | 4 |
| 17 | Backend tests | 8 |
| 18 | Final integration + cleanup | 4 |
| **Total** | | **110 steps** |
