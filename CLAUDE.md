# Draplo — Claude Code Context

**Current Year: 2026**

## Project Overview

Draplo (draplo.com) is an open-source platform where developers describe their SaaS idea through a guided wizard, and AI generates a complete, deployment-ready Laravel project scaffold optimized for AI coding agents (Claude Code, Cursor, Aider). Name origin: **Dra**(ft) + De**plo**(y) — draft your idea, deploy your scaffold.

**This is NOT a no-code builder.** Draplo generates a professional developer scaffold — the same quality output a senior Laravel architect would produce in 2 weeks of planning. The developer still writes the actual application code with their AI agent.

**License:** AGPL-3.0 — fully open source. Anyone can self-host for free.

**Business model (Open Core):**
- **Self-hosted:** Free forever. All features included. Clone repo, configure API keys, deploy to own server
- **Draplo Cloud — Free:** AI generation with generic skeleton + preview. No export
- **Draplo Cloud — Pro ($29 one-time):** Unlimited generations + GitHub export + ZIP download
- **Draplo Cloud — Pro+ ($12/mo):** All Pro features + 25 premium industry templates + BYOS deploy automation

**Core flow:** User selects a template from library (25 templates covering 95% of SaaS ideas) → wizard opens pre-populated with template defaults → user customizes for their specific use case → AI generates scaffold = template foundation (80%) + user customizations (20%) → export to GitHub → deploy via BYOS (Bring Your Own Server)

**Deploy model: BYOS (Bring Your Own Server)**
Draplo does NOT host user applications. Users bring their own server (Hetzner, DigitalOcean, Linode, Vultr — any provider). Draplo automates: Coolify installation on their server, app creation, database provisioning, SSL, and deployment. The user owns their server, their code, their data. If they cancel Draplo, their app keeps running. Zero vendor lock-in.

**Tagline:** "Draft it. Deploy it." or "Your next SaaS, architected by AI."

**Template library:** See `.claude-reference/features/template-library.md` for full spec of all 25 templates

## Stack

| Layer        | Technology                           |
|-------------|--------------------------------------|
| Backend     | Laravel 12 + PHP 8.3+               |
| Frontend    | React 18 (wizard SPA) + Blade (landing) |
| Styling     | Tailwind CSS 4                       |
| Database    | PostgreSQL 16                        |
| Cache/Queue | Redis 7 + Laravel Horizon            |
| AI          | Anthropic Claude Sonnet 4.6 API      |
| Auth        | Laravel Sanctum + GitHub OAuth       |
| Git         | GitHub API (repo creation, file push)|
| Deploy      | Coolify API (on user's server)       |
| Payments    | Stripe (one-time + subscription)     |
| Realtime    | Laravel Reverb (WebSocket, generation progress) |
| Storage     | Local filesystem (generated files)   |
| Hosting     | Hetzner VPS + Coolify (for Draplo itself) |
| 3D/Visual   | Three.js (landing page hero element) |

## Design System: "The Monolithic Architect"

**CRITICAL:** Read `.claude-ui-reference/DESIGN.md` before writing ANY frontend code. The design system is opinionated and specific.

**Core principles:**
- Dark mode ONLY (no light mode in v1)
- "Technical Brutalism" — high-contrast typography, vast negative space, no standard UI crutches
- NO 1px solid borders for sectioning — use background color shifts and negative space instead
- "Ghost Borders" only when containment is required (1px outline-variant at 15% opacity)
- Glass panels with backdrop-blur for floating elements and CTAs
- Terminal-style components for code output, AI logs, CLI previews

**Typography:**
- Headlines/display: Inter (tight tracking -0.02em)
- Body text: Inter
- Labels/metadata: Space Grotesk
- Code/numbers/data: Berkeley Mono (monospace) — this is the signature element
- Load from Google Fonts: `Inter:wght@400;500;600;700;800;900`, `Space+Grotesk:wght@300;400;500;600;700`
- Berkeley Mono must be self-hosted or use fallback `monospace`

**Color tokens (use Tailwind config from design screens):**
- Background/surface: `#121316` (background), `#0d0e11` (lowest), `#1b1b1f` (low), `#1f1f23` (container), `#292a2d` (high), `#343538` (highest)
- Primary: `#c0c1ff` (text/accent), `#8083ff` (container/gradient), `#494bd6` (inverse)
- Secondary: `#4cd7f6` (cyan accent), `#03b5d3` (container)
- Tertiary: `#ffb4a8` (warning text), `#ff5542` (container — used for "Deploy Now" CTA)
- On-surface: `#e3e2e6` (primary text), `#c7c4d7` (variant/muted)
- Outline: `#908fa0` (visible), `#464554` (variant/subtle)

**Icons:** Material Symbols Outlined (NOT emoji! The design explicitly uses Material icons and custom SVGs, not standard emoji)

**Landing page Three.js hero element:**
The landing page MUST feature an interactive 3D visual using Three.js instead of static images. The 3D element should feel "engineered" — geometric, precise, not organic/blobby. Concepts:
- Animated 3D scaffold/architecture being assembled — geometric blocks/layers stacking, connecting with glowing edges
- Abstract structure that morphs between different SaaS architecture shapes
- Interactive particle system that responds to mouse movement, forming code structure outlines
- Use primary (#c0c1ff) and primary-container (#8083ff) as glow/emission colors
- Keep performance in mind — 60fps on mid-range hardware

**Reference screens:** `.claude-ui-reference/screens/` contains HTML mockups and screenshots for all screens. These are the AUTHORITATIVE visual reference. Match them in implementation.

## How to Start Each Session

1. Read this file — understand the project context
2. Read `PROJECT.md` — understand features, roles, pricing
3. Read `todo.md` — find current phase and pending work
4. Check `.claude-reference/architecture.md` — DB schema & API endpoints
5. Check `.claude-ui-reference/DESIGN.md` — design system rules (BEFORE any frontend work)
6. Check `.claude-ui-reference/screens/` — visual reference for each screen

## Project-Specific Rules

1. **Tenant scope is NOT used in Draplo itself** — multi-tenancy is generated in OUTPUT projects for users
2. **All generated files stored on local filesystem** — no S3/MinIO for MVP
3. **API-first architecture** — React SPA consumes REST endpoints. Blade only for landing page (SEO)
4. **GitHub OAuth is the ONLY auth method** — all target users have GitHub
5. **Queued generation is mandatory** — Claude API takes 15-30s, must be async. Horizon + Redis. Progress via Reverb WebSocket
6. **Prompt caching is mandatory** — three layers: base (cached) + template (cached per template) + user customization (unique)
7. **XML tags for AI output parsing, NOT JSON** — PHP code in output breaks JSON. Use `<file path="...">content</file>`
8. **Feature flags control everything** — see Feature Flags section below
9. **No emoji in UI** — use Material Symbols Outlined or custom SVGs per design system
10. **Follow "No-Line Rule"** — no 1px solid borders for layout. Use background color shifts, spacing, tonal transitions
11. **Berkeley Mono for all data** — timestamps, IDs, file sizes, model counts, status codes in monospace
12. **BYOS model** — we never store user server credentials long-term. Encrypt at rest, use only during deploy operations

## File Naming Conventions

- Controllers: `{Model}Controller.php` (singular)
- Form Requests: `{Action}{Model}Request.php`
- Services: `{Name}Service.php`
- Jobs: `{Action}{Model}Job.php`
- Events: `{Model}{Action}Event.php`
- Enums: `{Name}Enum.php` in `app/Enums/`
- React components: PascalCase in `resources/js/components/`
- React pages: PascalCase in `resources/js/pages/`

## Key Architecture Decisions

See `.claude-reference/decisions.md` for full rationale. Quick summary:
- **Sonnet 4.6 not Haiku** — quality matters for architecture generation
- **AGPL-3.0 not MIT** — prevents fork-rebrand-sell without contributing back
- **BYOS not managed hosting** — zero infra cost for us, zero legal liability, user owns everything
- **Coolify not Railway/Vercel** — open source PaaS, user controls server
- **One-time + subscription** — $29 one-time for export, $12/mo for templates + deploy
- **GitHub OAuth only** — simplest auth, gets repo permissions, 100% of target users have it
- **Three.js on landing** — differentiate from generic AI tool landing pages

## BYOS Deploy Architecture

1. User enters server provider API key in Draplo settings
2. On first deploy, Draplo provisions VPS via provider API, installs Coolify via SSH
3. Subsequent deploys go through Coolify API on user's server
4. User gets live URL in ~3 minutes
5. User can SSH into their server at any time — full control
6. Cancelling Draplo subscription does NOT affect running applications

**Supported providers:**
- Hetzner Cloud API: `https://api.hetzner.cloud/v1/`
- DigitalOcean API: `https://api.digitalocean.com/v2/`
- Linode API: `https://api.linode.com/v4/`
- Vultr API: `https://api.vultr.com/v2/`

**Coolify API (on user's server):**
```
POST /api/v1/applications           — Create application
POST /api/v1/databases              — Create PostgreSQL database
PUT  /api/v1/applications/{id}/env  — Set environment variables
POST /api/v1/applications/{id}/deploy — Trigger deployment
GET  /api/v1/applications/{id}/status — Check deploy status
```

**Security:** Server provider API keys are encrypted at rest (Laravel's `encrypt()`). Keys are decrypted only during active deploy operations. Never logged. Never exposed in UI after initial entry.

## Stripe Configuration

Two products:
1. **"Draplo Pro"** — $29 one-time payment, unlocks export forever
2. **"Draplo Pro+"** — $12/mo subscription, unlocks premium templates + BYOS deploy

Middleware: `EnsureUserIsPro` checks `users.stripe_one_time_payment_at`. `EnsureUserIsProPlus` checks active Stripe subscription.

## Feature Flags

```env
STRIPE_ENABLED=true
COOLIFY_ENABLED=true
GITHUB_ENABLED=true
PREMIUM_TEMPLATES_ENABLED=true
BYOS_HETZNER_ENABLED=true
BYOS_DIGITALOCEAN_ENABLED=true
BYOS_LINODE_ENABLED=true
BYOS_VULTR_ENABLED=true
THREEJS_HERO_ENABLED=true
```

Self-hosted users can disable any feature. Disabling Stripe makes everything free. Disabling BYOS providers hides them from the deploy UI.

## Open Source Strategy

- Core platform is AGPL-3.0 — fully open source, self-hostable
- Premium templates are stored separately, NOT in the open source repo
- Self-hosted users get ALL features except premium templates
- GitHub repo README serves as primary marketing to developers
