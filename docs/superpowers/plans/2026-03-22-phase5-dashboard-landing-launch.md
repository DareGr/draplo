# Phase 5 — Dashboard + Landing Page + Open Source + Launch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the public-facing landing page with Three.js 3D hero, user dashboard with stats, account settings, admin UI, and open source packaging (README, LICENSE, feature flags) to make Draplo launch-ready.

**Architecture:** Landing page is Blade (SEO) with Three.js bundled separately via Vite. Dashboard, Settings, and Admin are React SPA pages using existing API endpoints. Feature flags API enables conditional UI. Open source files (README, LICENSE, CONTRIBUTING) complete the GitHub presence.

**Tech Stack:** Laravel 12, Blade, Three.js, React 18, Tailwind CSS 4

**Spec:** `docs/superpowers/specs/2026-03-22-phase5-dashboard-landing-launch-design.md`

---

## File Structure

### New files
```
resources/views/landing.blade.php              — full landing page with 8 sections
resources/js/threejs-hero.js                   — Three.js 3D scaffold scene
resources/js/pages/Dashboard.jsx               — stat cards, recent projects, terminal log
resources/js/pages/Settings.jsx                — account settings
resources/js/pages/Admin.jsx                   — admin stats + AI settings
app/Http/Controllers/ConfigController.php      — GET /api/config/flags
resources/views/sitemap.blade.php              — XML sitemap
public/robots.txt                              — robots.txt
README.md                                      — project README
LICENSE                                        — AGPL-3.0
CONTRIBUTING.md                                — contribution guide
tests/Feature/FlagsTest.php
```

### Files to modify
```
vite.config.js                  — add threejs-hero.js entry
resources/js/app.jsx            — add routes, change default redirect
resources/js/components/Sidebar.jsx — update Dashboard link
routes/api.php                  — add flags endpoint
routes/web.php                  — add sitemap route
.env.example                    — verify all flags
```

---

## Task 1: Feature Flags API + Test

**Files:**
- Create: `app/Http/Controllers/ConfigController.php`
- Create: `tests/Feature/FlagsTest.php`
- Modify: `routes/api.php`
- Modify: `.env.example`

- [ ] **Step 1: Create ConfigController**

Create `app/Http/Controllers/ConfigController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function flags(): JsonResponse
    {
        return response()->json([
            'stripe_enabled' => (bool) config('app.flags.stripe', true),
            'coolify_enabled' => (bool) config('app.flags.coolify', true),
            'github_enabled' => (bool) config('app.flags.github', true),
            'premium_templates_enabled' => (bool) config('app.flags.premium_templates', true),
            'threejs_hero_enabled' => (bool) config('app.flags.threejs_hero', true),
            'byos_hetzner_enabled' => (bool) config('app.flags.byos_hetzner', true),
        ]);
    }
}
```

- [ ] **Step 2: Add flags config**

Create or update `config/app.php` — add a `flags` key to the return array (read from .env):

```php
'flags' => [
    'stripe' => env('STRIPE_ENABLED', true),
    'coolify' => env('COOLIFY_ENABLED', true),
    'github' => env('GITHUB_ENABLED', true),
    'premium_templates' => env('PREMIUM_TEMPLATES_ENABLED', true),
    'threejs_hero' => env('THREEJS_HERO_ENABLED', true),
    'byos_hetzner' => env('BYOS_HETZNER_ENABLED', true),
],
```

- [ ] **Step 3: Add route**

In `routes/api.php`, add before the auth group (public endpoint):
```php
use App\Http\Controllers\ConfigController;

Route::get('/config/flags', [ConfigController::class, 'flags']);
```

- [ ] **Step 4: Verify .env.example has all flags**

Ensure these are present in `.env.example`:
```env
# Feature Flags
STRIPE_ENABLED=true
COOLIFY_ENABLED=true
GITHUB_ENABLED=true
PREMIUM_TEMPLATES_ENABLED=true
THREEJS_HERO_ENABLED=true
BYOS_HETZNER_ENABLED=true
```

- [ ] **Step 5: Write FlagsTest**

Create `tests/Feature/FlagsTest.php`:

```php
<?php

it('returns feature flags', function () {
    $this->getJson('/api/config/flags')
        ->assertOk()
        ->assertJsonStructure([
            'stripe_enabled',
            'coolify_enabled',
            'github_enabled',
            'premium_templates_enabled',
            'threejs_hero_enabled',
            'byos_hetzner_enabled',
        ]);
});

it('flags are boolean values', function () {
    $response = $this->getJson('/api/config/flags');
    $flags = $response->json();

    foreach ($flags as $value) {
        expect($value)->toBeBool();
    }
});
```

- [ ] **Step 6: Run tests**

```bash
php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add feature flags API endpoint with tests"
```

---

## Task 2: Landing Page (Blade — all sections except Three.js)

**Files:**
- Modify: `resources/views/landing.blade.php` (replace placeholder)
- Create: `resources/views/sitemap.blade.php`
- Create: `public/robots.txt`
- Modify: `routes/web.php`

- [ ] **Step 1: Create the full landing page**

Replace `resources/views/landing.blade.php` with the complete landing page. It must include:

1. **Head:** SEO meta tags (title, description, og:*, twitter:card), Google Fonts (Inter, Space Grotesk, Material Symbols), Tailwind via `@vite(['resources/css/app.css'])`

2. **Hero section:** Full-width with `<canvas id="hero-canvas">` for Three.js (positioned absolute behind content). Headline "Your next SaaS, architected by AI." in `text-6xl font-extrabold text-white`. Subheadline. Two CTA buttons: "Browse Templates" (primary gradient, href=/templates) and "View on GitHub" (secondary ghost border, href to GitHub). `min-h-screen` with content centered.

3. **Tech Stack Bar:** Horizontal flex with Material Symbol icons + monospace labels for: Laravel, PostgreSQL, React, Tailwind, Claude, Coolify. `bg-surface-container-low` with ghost borders.

4. **How It Works:** 4-column grid. Each: Material Symbol icon in a `bg-primary/10 rounded-xl` container, step title bold, description text. Steps: Describe (edit_note), Generate (smart_toy), Preview (code), Deploy (rocket_launch).

5. **Featured Blueprints:** 6 template cards in 3x2 grid. Each card matches template library card design: icon, category badge, name, description, model count + complexity footer. Hardcoded data for: Booking, CRM, Invoice, Restaurant, Project Mgmt, E-commerce. "See All 25 Templates" link at bottom.

6. **Agent-Ready:** Two-column layout. Left: terminal card (`bg-surface-container-lowest font-mono text-xs`) showing `.claude-reference/` folder tree. Right: heading "Built for AI Coding Agents" with feature bullets.

7. **Pricing:** 3 cards in a row. Free ($0), Pro ($29), Pro+ ($12/mo). Each: price, feature list, CTA button. Pro card highlighted with primary border.

8. **Open Source:** Dark section with "Fully open source. AGPL-3.0." heading, "Self-host for free. Forever." subtext, GitHub CTA button.

9. **Footer:** Draplo logo, copyright `© 2026 Draplo`, nav links (Privacy, Terms, Security, Changelog) in monospace.

All styling uses design system tokens from `resources/css/app.css`. NO emoji — Material Symbols only. Dark mode only. Ghost borders, tonal layering.

The Three.js `<canvas>` is included but the script is loaded in the next task — for now it will just be an empty canvas.

- [ ] **Step 2: Create sitemap**

Create `resources/views/sitemap.blade.php`:
```xml
{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ url('/') }}</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>
    <url><loc>{{ url('/templates') }}</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>
</urlset>
```

- [ ] **Step 3: Create robots.txt**

Create `public/robots.txt`:
```
User-agent: *
Allow: /
Sitemap: https://draplo.com/sitemap.xml
```

- [ ] **Step 4: Add sitemap route to web.php**

Add before the SPA catch-all:
```php
Route::get('/sitemap.xml', function () {
    return response()->view('sitemap')->header('Content-Type', 'application/xml');
});
```

- [ ] **Step 5: Verify** — `php artisan serve` and visit `http://localhost:8000/`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add full landing page with all sections, sitemap, robots.txt"
```

---

## Task 3: Three.js Hero

**Files:**
- Create: `resources/js/threejs-hero.js`
- Modify: `vite.config.js`
- Modify: `resources/views/landing.blade.php` (add script tag)

- [ ] **Step 1: Install Three.js**

```bash
npm install three
```

- [ ] **Step 2: Add threejs-hero.js as Vite entry**

In `vite.config.js`, add to the `input` array:
```js
input: ['resources/css/app.css', 'resources/js/app.jsx', 'resources/js/threejs-hero.js'],
```

- [ ] **Step 3: Create threejs-hero.js**

Create `resources/js/threejs-hero.js` — the full Three.js scene:

The scene contains:
- **Camera:** PerspectiveCamera, positioned looking at the scaffold from a 3/4 angle
- **Renderer:** WebGLRenderer attached to `#hero-canvas`, transparent background
- **Lighting:** Subtle ambient light + point light with primary color tint
- **Geometry:** 8-12 BoxGeometry blocks of varying sizes (cubes, flat rectangles) representing architecture layers
- **Material:** MeshStandardMaterial with wireframe overlay. Base color dark (`#1b1b1f`), emissive primary (`#8083ff`) at low intensity. Wireframe lines in primary (#c0c1ff).
- **Animation:** Blocks start scattered and slowly assemble into a layered structure. Once assembled, they gently float/breathe (sine wave on Y position). Subtle rotation on each block.
- **Lines:** EdgesGeometry + LineSegments connecting blocks, pulsing opacity with secondary color (#4cd7f6)
- **Mouse interaction:** Track mouse position, offset camera X/Y slightly (parallax). Use `mousemove` event on window.
- **Resize:** Handle window resize, update camera aspect + renderer size
- **Performance:** Check `navigator.hardwareConcurrency < 4` or mobile UA → don't initialize, leave canvas empty (CSS gradient fallback handles it)
- **Feature flag:** Check a `data-enabled` attribute on the canvas element (set from Blade based on env)

The file should be self-contained — import from `three`, find `#hero-canvas`, initialize scene, animate.

Approximately 200-300 lines of JavaScript.

- [ ] **Step 4: Update landing.blade.php**

Add the Vite script tag for threejs-hero at the bottom of the body, and the feature flag data attribute on the canvas:

```blade
<canvas id="hero-canvas" class="absolute inset-0 w-full h-full" data-enabled="{{ config('app.flags.threejs_hero') ? 'true' : 'false' }}"></canvas>

@vite(['resources/js/threejs-hero.js'])
```

Add a CSS gradient fallback behind the canvas for when Three.js is disabled or loading:
```html
<div class="absolute inset-0 bg-gradient-to-br from-background via-surface-container-low to-background"></div>
```

- [ ] **Step 5: Verify build + visual**

```bash
npm run build
php artisan serve
# Visit http://localhost:8000/ — should see 3D blocks animating
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add Three.js 3D scaffold hero visualization"
```

---

## Task 4: Dashboard + Settings + Admin (React)

**Files:**
- Create: `resources/js/pages/Dashboard.jsx`
- Create: `resources/js/pages/Settings.jsx`
- Create: `resources/js/pages/Admin.jsx`
- Modify: `resources/js/app.jsx`
- Modify: `resources/js/components/Sidebar.jsx`

- [ ] **Step 1: Create Dashboard page**

`resources/js/pages/Dashboard.jsx`:

On mount: fetch `GET /api/auth/me` and `GET /api/projects`.

Layout within AppLayout (activePage="dashboard"):

**Top row (4 stat cards in grid-cols-4):**
Each card: `bg-surface-container rounded-xl p-5`, icon in `bg-primary/10 rounded-lg w-10 h-10`, value in `text-3xl font-extrabold font-mono`, label in `font-label text-[11px] uppercase tracking-widest text-outline`.

Cards: Total Projects (folder), Deployed (rocket_launch), Generations (smart_toy — count projects with generation_output), Plan (credit_card — user.plan badge).

**Middle: Recent Projects (last 5):**
List of project cards (same style as ProjectList but compact). Name, template, status chip, updated_at, quick action links.

**Bottom: AI Architect Terminal:**
`bg-surface-container-lowest rounded-xl p-5 font-mono text-xs text-on-surface-variant`. Header "Architect Log" with `terminal` icon. Show entries for projects that have been generated — format: `[date] {name} via {template} — {status}`. If no generations yet, show "No generation events yet."

- [ ] **Step 2: Create Settings page**

`resources/js/pages/Settings.jsx`:

On mount: fetch `GET /api/auth/me` and `GET /api/servers`.

Layout within AppLayout (activePage="settings"):

**Profile card:** Avatar image (or placeholder icon), name, email, github_username. All read-only, displayed in Card component.

**Plan card:** Plan badge (Free/Pro/Pro+). "Upgrade" text link to `/#pricing`.

**GitHub card:** "Connected" green dot + username. "Reconnect GitHub" link to `/auth/github`.

**Servers card:** List servers with name, IP, status dot, provider badge. "Add Server" button linking to deploy page. Health check button per server (calls `GET /api/servers/{id}/health`, shows toast).

**Danger Zone card:** Red-tinted card with disabled "Delete Account" button + "Coming soon" tooltip.

- [ ] **Step 3: Create Admin page**

`resources/js/pages/Admin.jsx`:

On mount: fetch `GET /api/auth/me` (check is_admin, redirect if not), `GET /api/admin/stats`, `GET /api/admin/settings`.

Layout within AppLayout (activePage="admin" — add to sidebar for admin users):

**Stats section (grid-cols-3):**
Cards: Users, Projects, Generations, Total Cost ($X.XX), Generations Today, Active Provider + Model.

**AI Settings section:**
Form card with:
- Provider dropdown (Anthropic / Gemini)
- Model name input
- Max tokens number input
- Rate limit number input
- "Save Settings" button → `PUT /api/admin/settings`
- Toast on success/error

- [ ] **Step 4: Update app.jsx**

Add imports and routes:
```jsx
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';
import Admin from './pages/Admin';

// Routes (before catch-all):
<Route path="/dashboard" element={<Dashboard />} />
<Route path="/settings" element={<Settings />} />
<Route path="/admin" element={<Admin />} />

// Change catch-all from /templates to /dashboard:
<Route path="*" element={<Navigate to="/dashboard" replace />} />
```

- [ ] **Step 5: Update Sidebar**

In `resources/js/components/Sidebar.jsx`, update Dashboard nav item link from `/projects` to `/dashboard`.

- [ ] **Step 6: Verify build**

```bash
npm run build
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add Dashboard, Settings, Admin pages with routing"
```

---

## Task 5: Open Source Files (README + LICENSE + CONTRIBUTING)

**Files:**
- Create: `README.md` (replace existing or create)
- Create: `LICENSE`
- Create: `CONTRIBUTING.md`

- [ ] **Step 1: Create README.md**

Comprehensive developer README with:
- Project name + tagline + description
- Feature list (wizard, AI generation, preview, export, deploy)
- Tech stack table
- Quick start (docker-compose, env, migrate, seed, serve)
- Feature flags table with all flags
- Self-hosting guide (requirements, API keys, GitHub OAuth)
- Contributing link
- License

~200-300 lines of markdown.

- [ ] **Step 2: Create LICENSE**

Full AGPL-3.0 license text. Standard boilerplate from https://www.gnu.org/licenses/agpl-3.0.txt with Draplo copyright header.

- [ ] **Step 3: Create CONTRIBUTING.md**

Short contribution guide:
- Prerequisites
- Dev setup steps
- Code style
- PR process
- Testing requirements

- [ ] **Step 4: Commit**

```bash
git add README.md LICENSE CONTRIBUTING.md
git commit -m "feat: add README, LICENSE (AGPL-3.0), CONTRIBUTING for open source"
```

---

## Task 6: Final Verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: All tests pass (~106+).

- [ ] **Step 2: Verify frontend build**

```bash
npm run build
```

- [ ] **Step 3: Verify landing page**

```bash
php artisan serve
# Visit http://localhost:8000/ — full landing page with Three.js hero
```

- [ ] **Step 4: Verify SPA routes**

Visit:
- `/dashboard` — stat cards, recent projects
- `/settings` — profile, GitHub, servers
- `/admin` — stats, AI settings
- `/templates` — template library
- `/projects` — project list

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: Phase 5 complete — Dashboard, Landing Page, Open Source, Launch-ready"
```

---

## Summary

| Task | Description | Key Files |
|------|-------------|-----------|
| 1 | Feature flags API + test | ConfigController, FlagsTest, api.php |
| 2 | Landing page (Blade, all sections) | landing.blade.php, sitemap, robots.txt |
| 3 | Three.js 3D hero | threejs-hero.js, vite.config.js |
| 4 | Dashboard + Settings + Admin (React) | 3 pages, app.jsx, Sidebar |
| 5 | Open source (README + LICENSE + CONTRIBUTING) | 3 files |
| 6 | Final verification | Tests + build + visual check |
