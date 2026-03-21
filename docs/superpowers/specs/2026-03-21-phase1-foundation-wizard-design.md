# Phase 1 — Foundation + Wizard UI Design Spec

**Date:** 2026-03-21
**Status:** Approved
**Scope:** Laravel project setup, database, models, React wizard SPA, Template Library page, wizard API

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Template-to-wizard flow | A/C hybrid: Library = standalone page, wizard = 6 steps | Matches UI mockups (library has own sidebar nav) and constants.md (6 steps). Library is entry point, wizard focuses on customization |
| AI suggest-models | Deferred to Phase 2 | Requires AnthropicService, API key config — all Phase 2 work. Models step works with template defaults + manual editing |
| Auth in Phase 1 | Dev-mode auth (seeded user + Sanctum token) | GitHub OAuth deferred to Phase 3. Middleware and User model built for OAuth — just skip the flow for now |
| Local development | Docker Compose required | `docker-compose up` starts PostgreSQL 16, Redis 7, app. Consistent environment for all developers |
| Implementation approach | Vertical slices | Foundation first, then each wizard step as full API + UI slice. Template Library last |

---

## 1. Foundation & Infrastructure

### Docker Compose

Services:
- **postgres**: PostgreSQL 16, port 5432, volume for data persistence
- **redis**: Redis 7, port 6379
- **app**: PHP 8.3 + Laravel 12 (or use `php artisan serve` locally with Docker for DB/Redis only)

Single `docker-compose up` starts everything.

### Laravel 12 Setup

- **Sanctum**: API token authentication
- **Horizon**: Queue dashboard, configured for Redis. Generation jobs are Phase 2 but queue infrastructure is ready
- **Reverb**: Config stubbed for Phase 2 WebSocket support
- **Vite + React 18 + Tailwind CSS 4**: SPA frontend

### Routing Split

- `routes/web.php`: Landing page placeholder, SPA catch-all, dev-mode login route
- `routes/api.php`: All wizard/project endpoints under `auth:sanctum` middleware

### Dev-Mode Auth

- Seeded admin user with stubbed GitHub fields
- `GET /dev/login` — returns Sanctum token for seeded user. Guarded by `APP_ENV=local`
- React stores token in localStorage, sends as `Authorization: Bearer` header

---

## 2. Database Schema & Models

### Migrations

**users**
| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL PK | |
| name | VARCHAR(255) | |
| email | VARCHAR(255) | UNIQUE |
| password | VARCHAR(255) | Nullable (GitHub OAuth users) |
| github_id | VARCHAR(100) | |
| github_token | TEXT | Encrypted |
| github_username | VARCHAR(100) | |
| avatar_url | VARCHAR(500) | |
| stripe_customer_id | VARCHAR(100) | |
| plan | VARCHAR(50) | Default: 'free' |
| paid_at | TIMESTAMP | Nullable |
| generation_count | INTEGER | Default: 0 |
| timestamps | | |

**projects**
| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL PK | |
| user_id | BIGINT FK | → users.id |
| name | VARCHAR(255) | |
| slug | VARCHAR(100) | |
| description | TEXT | |
| wizard_data | JSONB | Complete wizard input |
| generation_output | JSONB | Cached AI output |
| skeleton_version | VARCHAR(20) | |
| input_hash | VARCHAR(64) | SHA-256 for caching |
| github_repo_url | VARCHAR(500) | |
| github_repo_name | VARCHAR(200) | |
| coolify_app_id | VARCHAR(100) | |
| coolify_db_id | VARCHAR(100) | |
| deploy_url | VARCHAR(500) | |
| custom_domain | VARCHAR(255) | |
| status | VARCHAR(50) | Default: 'draft' |
| exported_at | TIMESTAMP | Nullable |
| deployed_at | TIMESTAMP | Nullable |
| timestamps | | |

Indexes: `(user_id, status)`, `(input_hash)`

**generations**
| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL PK | |
| project_id | BIGINT FK | → projects.id |
| input_hash | VARCHAR(64) | |
| prompt_tokens | INTEGER | |
| completion_tokens | INTEGER | |
| cost_usd | DECIMAL(8,4) | |
| model | VARCHAR(100) | |
| duration_ms | INTEGER | |
| cached | BOOLEAN | |
| created_at | TIMESTAMP | |

No `stripe_events` or `server_connections` in Phase 1.

### Enums (`app/Enums/`)

**UserPlanEnum:** `free`, `paid`, `subscriber`

**ProjectStatusEnum:** `draft`, `wizard_done`, `generating`, `generated`, `exported`, `deploying`, `deployed`, `failed`

### Models

**User:**
- Casts: `plan` → `UserPlanEnum`, encrypts `github_token`
- Relationships: `hasMany(Project)`
- Helpers: `isFree()`, `isPaid()`, `isSubscriber()`

**Project:**
- Casts: `wizard_data` → array, `generation_output` → array, `status` → `ProjectStatusEnum`
- Relationships: `belongsTo(User)`, `hasMany(Generation)`

**Generation:**
- Casts: `cached` → boolean
- Relationships: `belongsTo(Project)`

---

## 3. Wizard API Endpoints

All under `auth:sanctum` middleware.

### `POST /api/wizard/projects`

Create a new draft project.

- Input: `{ template_slug?: string }`
- If `template_slug` provided, loads `wizard-defaults.json` from `storage/app/templates/{slug}/` and seeds `wizard_data`
- Sets `status: draft`, generates slug
- Returns: project with pre-populated `wizard_data`

### `PUT /api/wizard/projects/{id}`

Save wizard state per step.

- Input: `{ step: string, data: object }`
- Merges `data` into `wizard_data` JSON at the step key
- Validates project belongs to authenticated user
- If step is `review` (final), sets `status: wizard_done`
- Returns: updated project

### `GET /api/wizard/projects/{id}`

Load project with wizard data for resuming.

- Validates ownership
- Returns: full project including `wizard_data`

### `GET /api/projects`

List user's projects.

- Returns: projects with status, name, template, updated_at
- For dashboard and "resume wizard" functionality

### Form Validation (`UpdateWizardRequest`)

- `step` must be one of: `describe`, `users`, `models`, `auth`, `integrations`, `review`
- `data` must be an object
- Per-step rules: `describe` requires non-empty `name`, `models` requires at least one model with a name

### TemplateService

- Reads `template.json` and `wizard-defaults.json` from `storage/app/templates/{slug}/`
- `getDefaults(slug)`: returns wizard defaults for pre-populating
- `listTemplates()`: returns all template metadata for library grid
- Phase 1: only Booking Platform has full defaults. Other 24 have metadata only.

---

## 4. React SPA Architecture

### Entry Point

`app.blade.php` mounts `<div id="app">`, Vite loads `resources/js/app.jsx`.

### Routes (React Router v6)

| Path | Component | Description |
|------|-----------|-------------|
| `/templates` | TemplateLibrary | Browsable template grid |
| `/wizard/:projectId` | WizardLayout → Step components | 6-step wizard flow |
| `/projects` | ProjectList | List of user's projects |
| `*` | Redirect to `/templates` | Catch-all |

### State Management

No Redux/Zustand. Wizard state lives in the API (`projects.wizard_data`). Each step loads from API on mount, saves on "Next". Local component state for in-progress edits. Free resume-wizard functionality.

### Auth Flow

On app boot, check localStorage for token. If missing in dev mode, call `/dev/login` automatically. Axios instance with `Authorization: Bearer` header globally.

### Layout Components

**AppLayout.jsx** — sidebar nav + top bar + main content area

Sidebar items: Dashboard, Library, Wizard, Deployments, Settings. Wizard progress widget when inside wizard (step X of 6, progress bar, template name).

Top bar: Draplo logo, nav links (Templates, Pricing, Docs, OS), Sign In / Deploy Now buttons.

**WizardLayout.jsx** — wraps all steps, provides:
- Step navigation
- Bottom bar: Back / Save Draft / Next buttons
- Progress indicator
- Passes `projectId` from URL to step components

### Base Components (Design System)

| Component | Description |
|-----------|-------------|
| `Button` | Primary (gradient from `#c0c1ff` to `#8083ff`), secondary (ghost border), tertiary (text only). `rounded-md` shape. |
| `Input` | Monospace text, ghost border (1px outline-variant at 15% opacity), primary glow on focus |
| `Card` | Tonal layering (`surface-container` → `surface-container-high` on hover), no solid borders |
| `Chip` | Status dot (colored circle) + monospace label |
| `CategoryFilter` | Horizontal scrollable pill buttons for template filtering |

### Fonts

Loaded in `app.blade.php`:
- Inter + Space Grotesk from Google Fonts
- Berkeley Mono with `monospace` fallback

---

## 5. Wizard Step Details

### Step 1 — Describe Your App (`StepDescribe`)

Fields:
- `name` (text input, required)
- `description` (textarea)
- `problem` (textarea — "What problem does this solve?")

Pre-populated from template's `step_describe` defaults.

### Step 2 — Who Uses It? (`StepUsers`)

Fields:
- `app_type` — pill button selector (B2B SaaS, B2C App, Marketplace, Internal Tool, API Service)
- `roles` — list with `name`, `description`, `removable`, `renameable` flags
- Pre-populated from template. User can rename/remove/add roles.

### Step 3 — Core Models (`StepModels`)

Matches `wizard_core_models` UI mockup:
- List of model cards with name, fields as chips, description
- Locked models: lock badge, can't delete
- Field chips with type indicators (FK, T, #, $, ?)
- "Add field" per model, "Add New Core Model" at bottom
- Drag handle (visual only in Phase 1)
- Floating relationships widget on desktop (auto-detected from FK fields)

### Step 4 — Auth & Tenancy (`StepAuth`)

Fields:
- `multi_tenant` toggle
- `auth_method` selector (Sanctum default, only option for now)
- `guest_access` toggle + description field
- Pre-populated from template

### Step 5 — Integrations (`StepIntegrations`)

- Grid of integration cards with toggles
- Options: Stripe, SMS, Email, File Storage, AI, Full-Text Search, WebSockets
- Pre-checked from template defaults
- Each card shows what it generates
- Optional notes textarea

### Step 6 — Review & Generate (`StepReview`)

- Read-only summary of all steps, expandable/collapsible sections
- "Edit" link per section jumps back to that step
- "Generate" button disabled — shows "Coming in Phase 2" tooltip
- Sets project status to `wizard_done`

---

## 6. Template Library Page

### Layout

Full page within `AppLayout`. No wizard bottom bar.

### Header

Eyebrow label in Berkeley Mono, large title "Template Library", subtitle. Matches mockup.

### Category Filter

Horizontal scrollable pills: All, Operations, Sales, Content, Platform, Education, Health, Hospitality, Analytics, Specialized. Client-side filtering.

### Template Grid

3 columns desktop, 2 tablet, 1 mobile.

**"Start from Scratch" card:** Dashed border, centered. Creates project with no template, enters wizard with empty defaults.

**Template cards** (per mockup):
- Material Symbol icon + category badge
- Template name (bold, primary on hover)
- Description (2 lines)
- Tag chips (monospace)
- Footer: model count (cyan dot) + complexity (amber/red dot) + arrow
- Hover: `surface-container` → `surface-container-high`

### Click Behavior

Clicking a template:
1. `POST /api/wizard/projects` with `template_slug`
2. Receives project with pre-populated `wizard_data`
3. Redirects to `/wizard/{projectId}` — Step 1

**Phase 1 data:** Booking Platform has full `wizard-defaults.json`. Other 24 templates have `template.json` metadata only — render in grid but show "Coming soon" toast on click.

---

## 7. Testing & Dev Experience

### Backend Tests (Pest PHP)

**Feature/WizardTest.php:**
- Create project with template, create without template
- Update wizard data per step
- Load project with wizard data
- Ownership validation (can't access other user's project)
- Step validation rules (invalid step, missing required fields)

**Feature/AuthTest.php:**
- Dev-mode login works when `APP_ENV=local`
- Returns valid Sanctum token
- Blocked when `APP_ENV=production`

**Unit/TemplateServiceTest.php:**
- Loads Booking Platform defaults correctly
- Returns null for unknown template slug
- Lists all available templates with metadata

### No Frontend Tests in Phase 1

React testing (Vitest, Testing Library, MSW) deferred to avoid setup overhead. Manual browser testing is sufficient while wizard is being built.

### Dev Workflow

```bash
docker-compose up                # Start PostgreSQL, Redis
cp .env.example .env            # Configure environment
php artisan migrate --seed       # Create tables + dev user + template data
npm run dev                      # Vite HMR for React
php artisan serve                # Laravel dev server
```

### Seeders

- **DevUserSeeder:** Test user (name: "Dev User", email: "dev@draplo.test", plan: free)
- **TemplateSeeder:** Copies Booking Platform template files to `storage/app/templates/booking-platform/`

---

## Implementation Order (Vertical Slices)

1. Foundation: Laravel 12, Docker Compose, Vite + React 18, Tailwind 4, Sanctum, Horizon config
2. Database: migrations + models + enums + dev-mode auth + seeders
3. Wizard shell: AppLayout, WizardLayout, step navigation, bottom nav, progress bar
4. Step 1 (Describe) — API endpoint + React UI + persistence
5. Step 2 (Users) — API endpoint + React UI + persistence
6. Step 3 (Models) — API endpoint + React UI + persistence
7. Step 4 (Auth) — API endpoint + React UI + persistence
8. Step 5 (Integrations) — API endpoint + React UI + persistence
9. Step 6 (Review) — summary view, status update to wizard_done
10. Template Library page — grid, filtering, "Use this template" flow
11. Backend tests

---

## Out of Scope (Phase 1)

- AI generation (Phase 2)
- AI model suggestions (Phase 2)
- GitHub OAuth (Phase 3)
- GitHub export / ZIP download (Phase 3)
- Stripe payments (Phase 3)
- BYOS deploy / Coolify (Phase 4)
- Landing page / Three.js hero (Phase 5)
- Dashboard with stats (Phase 5)
- Drag-and-drop model reordering (nice-to-have, not required)
- Frontend automated tests (deferred)
