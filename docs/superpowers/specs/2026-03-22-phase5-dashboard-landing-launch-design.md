# Phase 5 — Dashboard + Landing Page + Open Source + Launch Design Spec

**Date:** 2026-03-22
**Status:** Approved
**Scope:** Landing page with Three.js hero, dashboard with stats, account settings, admin UI, open source prep (README, LICENSE, feature flags, SEO)
**Depends on:** All previous phases complete (1-4)

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Three.js hero | Full interactive 3D scaffold visualization | Differentiates from generic AI landing pages. Geometric blocks assembling, mouse-responsive. Feature-flagged with static gradient fallback. |
| Dashboard vs Projects | Separate routes | `/dashboard` for stats/overview, `/projects` for full list. Matches sidebar navigation and mockup. |
| README | Text-only for launch | Screenshots added post-launch. Developer audience values clear setup instructions over visuals. |
| Open source packaging | README + LICENSE + CONTRIBUTING + feature flags | Minimum viable open source. Feature flags let self-hosters disable any integration. |
| Admin UI | Simple React page using existing API | Admin API (settings, stats) already built in Phase 2A. Just needs a frontend. |

---

## 1. Landing Page (Blade + Three.js)

**Route:** `/` — `resources/views/landing.blade.php` (Blade for SEO, not React)

### Three.js Hero

Interactive 3D visualization loaded via `resources/js/threejs-hero.js` (dynamic import, not part of React SPA bundle).

**Scene:**
- Dark void background matching `#121316`
- Floating geometric blocks (cubes, flat rectangles, L-shapes) representing architecture layers
- Blocks assemble from scattered positions into a layered scaffold structure
- Wireframe edges glow in primary (#c0c1ff) and primary-container (#8083ff)
- Connecting lines between blocks pulse with secondary (#4cd7f6) color
- Mouse movement shifts camera position subtly (parallax)
- Animation loop: blocks slowly rotate, drift, and reconnect

**Technical:**
- Three.js loaded via CDN `<script>` or bundled separately via Vite entry point
- `<canvas id="hero-canvas">` positioned absolute behind hero content
- OrbitControls disabled — only mouse-track camera offset
- Responsive: resize on window resize
- Performance: max 60fps, use `requestAnimationFrame`, limit geometry complexity
- Mobile: detect low-power (< 4 cores or mobile UA) → show static gradient fallback
- Feature flag: `THREEJS_HERO_ENABLED` env var. If false, canvas hidden, gradient shown.

**Vite entry:** Add `resources/js/threejs-hero.js` as a separate Vite input (not bundled with React app). Load in landing.blade.php only.

### Landing Page Sections

**1. Hero:**
- Headline: "Your next SaaS, architected by AI." (`text-6xl font-extrabold`)
- Subheadline: "Describe your idea. AI generates the scaffold. Deploy to your server." (`text-xl text-on-surface-variant`)
- CTAs: "Browse Templates" (primary gradient button → `/templates`), "View on GitHub" (secondary ghost → GitHub URL)
- Three.js canvas behind content

**2. Tech Stack Bar:**
- Horizontal row: Laravel, PostgreSQL, React, Tailwind, Claude, Coolify
- Each: icon/logo + monospace label
- `bg-surface-container-low` bar with ghost border top/bottom

**3. Process Section — "How It Works":**
- 4 steps in a grid:
  1. "Describe" (icon: `edit_note`) — "Tell the wizard about your SaaS idea"
  2. "Generate" (icon: `smart_toy`) — "AI architects your complete scaffold"
  3. "Preview" (icon: `code`) — "Review and edit every generated file"
  4. "Deploy" (icon: `rocket_launch`) — "Push to GitHub, deploy to your server"

**4. Featured Blueprints:**
- 6 template cards (Booking, CRM, Invoice, Restaurant, Project Mgmt, E-commerce)
- Same card design as Template Library but smaller
- "See All 25 Templates" link → `/templates`

**5. Agent-Ready Section:**
- "Built for AI Coding Agents"
- Left: terminal card showing `.claude-reference/` folder structure
- Right: description of what agents get (context, architecture, todos, patterns)

**6. Pricing Section:**
- 3 tier cards:
  - Free ($0): "AI generation + preview"
  - Pro ($29 one-time): "GitHub export + ZIP download"
  - Pro+ ($12/mo): "Premium templates + BYOS deploy"
- Note: informational only, Stripe not implemented

**7. Open Source Section:**
- "Fully open source. AGPL-3.0."
- "Self-host for free. Forever."
- GitHub stars button/link

**8. Footer:**
- Draplo logo + copyright
- Links: Privacy, Terms, Security, Changelog

### SEO Meta Tags

```html
<title>Draplo — Your next SaaS, architected by AI</title>
<meta name="description" content="Describe your SaaS idea, AI generates a complete Laravel scaffold optimized for AI coding agents. Open source, self-hostable.">
<meta property="og:title" content="Draplo — Draft it. Deploy it.">
<meta property="og:description" content="AI-generated Laravel project scaffolds for developers.">
<meta property="og:image" content="/images/og-image.png">
<meta property="og:url" content="https://draplo.com">
<meta name="twitter:card" content="summary_large_image">
<link rel="canonical" href="https://draplo.com">
```

---

## 2. Dashboard (React SPA)

**Route:** `/dashboard` — within AppLayout, activePage="dashboard"

### Stat Cards (top row, 4 columns)

| Card | Value | Icon | Source |
|------|-------|------|--------|
| Total Projects | count | `folder` | GET /api/projects (count) |
| Deployed | count where status=deployed | `rocket_launch` | GET /api/projects (filter) |
| Generations | total | `smart_toy` | GET /api/admin/stats (admin) or count from projects |
| Plan | badge | `credit_card` | GET /api/auth/me → plan field |

Card styling: `bg-surface-container rounded-xl p-5`, value `text-3xl font-extrabold font-mono text-white`, label `font-label text-[11px] uppercase tracking-widest text-outline`.

### Recent Projects (middle)

Table/list of last 5 projects:
- Name (bold), template slug (monospace), status chip, updated_at (monospace)
- Quick actions: Preview / Deploy links
- "View All Projects" link → `/projects`

### AI Architect Terminal (bottom)

Terminal-style card showing recent generation events:
- `bg-surface-container-lowest rounded-xl p-5 font-mono text-xs`
- Each entry: `[timestamp] Generated {project_name} via {provider}/{model} — {tokens} tokens, ${cost}, {duration}s`
- Last 5 generations from projects
- Gives the "architect's log" feel

### Data Loading

On mount:
1. `GET /api/auth/me` → user info + plan
2. `GET /api/projects` → all projects (derive stats client-side)

No new backend endpoints needed.

---

## 3. Account Settings

**Route:** `/settings` — within AppLayout, activePage="settings"

### Profile Section (read-only)
- Avatar (from GitHub)
- Name, email, GitHub username
- All from `GET /api/auth/me`

### Plan Section
- Current plan badge (Free/Pro/Pro+)
- "Upgrade" text (placeholder — links to pricing section on landing page)

### GitHub Connection
- Connected status with green dot + username
- "Reconnect GitHub" link → `/auth/github`

### Server Connections
- List from `GET /api/servers`
- Each: name, IP, status dot, provider badge
- "Add Server" link → opens in-context or links to deploy page
- Health check button per server

### Danger Zone
- "Delete Account" button (disabled, tooltip "Coming soon")

---

## 4. Admin UI

**Route:** `/admin` — within AppLayout, admin-only

Frontend checks `user.is_admin` from `GET /api/auth/me`. If not admin, redirect to `/dashboard`.

### Platform Stats
- Cards: users count, projects count, generations count, total cost USD
- Generations today count
- Active provider + model display
- All from `GET /api/admin/stats`

### AI Settings
- Provider dropdown: Anthropic / Gemini
- Model name input (text)
- Max tokens input (number)
- Rate limit input (number)
- "Save Settings" button → `PUT /api/admin/settings`
- Success/error toast on save
- All from `GET /api/admin/settings`

---

## 5. Open Source Prep

### README.md

Structure:
```markdown
# Draplo — Your next SaaS, architected by AI

> Draft it. Deploy it. Open source.

## What is Draplo?
[2-3 sentences]

## Features
- Guided wizard with 25 industry templates
- AI generation via Claude or Gemini
- Code preview with syntax highlighting + inline editing
- GitHub export + ZIP download
- BYOS deploy via Coolify (Hetzner auto-provision or any server)

## Quick Start
[docker-compose up, env setup, migrate, seed, serve]

## Tech Stack
[table: Laravel 12, React 18, PostgreSQL, Redis, etc.]

## Feature Flags
[table of all flags with descriptions]

## Self-Hosting Guide
[requirements, API keys, GitHub OAuth setup]

## Contributing
[link to CONTRIBUTING.md]

## License
AGPL-3.0
```

### LICENSE
Full AGPL-3.0 license text.

### CONTRIBUTING.md
- Prerequisites (PHP 8.3, Node 24, Docker)
- Setup steps
- Code style (follow existing patterns)
- PR process (fork, branch, PR against main)
- Testing requirements (all tests must pass)

### .env.example
Ensure all flags present with comments. Already partially done — just verify completeness.

### Feature Flags API

**New endpoint:** `GET /api/config/flags` (public, no auth)

Returns:
```json
{
    "stripe_enabled": true,
    "coolify_enabled": true,
    "github_enabled": true,
    "premium_templates_enabled": true,
    "threejs_hero_enabled": true,
    "byos_hetzner_enabled": true
}
```

**ConfigController** reads from `.env` via `config()`. React SPA fetches on boot to conditionally show/hide features.

### SEO

- Sitemap at `/sitemap.xml` (Blade view, lists: /, /templates, /pricing)
- robots.txt (allow all)

---

## 6. Testing

### Feature/FlagsTest.php
- GET /api/config/flags returns JSON with expected keys
- Flags reflect env values

### No other new backend tests
- Dashboard/settings/admin use existing tested APIs
- Landing page is Blade (no API tests needed)

---

## 7. File Structure

### New files
```
# Landing Page
resources/views/landing.blade.php              — full landing page (replace placeholder)
resources/js/threejs-hero.js                   — Three.js 3D scene

# Dashboard + Settings + Admin
resources/js/pages/Dashboard.jsx               — stat cards, recent projects, terminal log
resources/js/pages/Settings.jsx                — account settings
resources/js/pages/Admin.jsx                   — admin stats + AI settings editor

# Open Source
README.md                                      — project README (replace if exists)
LICENSE                                        — AGPL-3.0
CONTRIBUTING.md                                — contribution guide

# Feature Flags
app/Http/Controllers/ConfigController.php      — GET /api/config/flags

# SEO
resources/views/sitemap.blade.php              — XML sitemap
public/robots.txt                              — search engine directives

# Tests
tests/Feature/FlagsTest.php
```

### Files to modify
```
vite.config.js                                 — add threejs-hero.js as separate entry
resources/js/app.jsx                           — add /dashboard, /settings, /admin routes; change default redirect to /dashboard
resources/js/components/Sidebar.jsx            — update Dashboard link to /dashboard
routes/api.php                                 — add flags endpoint
routes/web.php                                 — add sitemap route
.env.example                                   — verify all flags documented
```

---

## 8. Out of Scope

- Screenshots in README (add post-launch)
- Stripe payments (deferred)
- Delete account functionality
- Advanced SEO (structured data, blog)
- Admin prompt editor with live preview (simple textarea + save)
- Production deployment (manual step)
- Custom OG image generation per project
- Analytics / tracking scripts
- Internationalization / i18n
