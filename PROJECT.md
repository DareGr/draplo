# Draplo

## What Is This?

Draplo is a platform that transforms a SaaS idea description into a complete, deployment-ready Laravel project scaffold optimized for AI coding agents. A developer describes their app through a guided wizard, AI generates the full project architecture (database schema, API endpoints, file structure, documentation), and the result is pushed to GitHub as a ready-to-clone repo. Optional one-click deploy via managed Coolify hosting. Think of it as "2 weeks of senior architect planning, delivered in 3 minutes."

## Core Features

- **Guided Wizard** — Step-by-step form where developer describes their SaaS: what it does, who uses it, core models, auth needs, integrations. Smart defaults and suggestions at each step
- **AI Architecture Generation** — Claude API generates 7 project documents tailored to the described app: CLAUDE.md, PROJECT.md, todo.md, architecture.md (DB schema + API endpoints), constants.md, patterns.md, decisions.md
- **Laravel Skeleton** — Static boilerplate merged with AI-generated files: Laravel 12 with Sanctum auth, multi-tenancy (stancl/tenancy), Docker Compose, deploy scripts, .claude-reference folder structure, test scaffolding
- **GitHub Integration** — OAuth login, one-click repo creation with all generated files pushed. Private repo by default
- **Project Preview** — Before paying/exporting, user can preview all generated files in a code viewer with syntax highlighting
- **Coolify Deploy (BYOS)** — Bring Your Own Server deploy. User provides their server API key (Hetzner, DigitalOcean, Linode, Vultr), Draplo provisions VPS, installs Coolify, creates PostgreSQL database, configures SSL, deploys from GitHub. User owns their server completely — zero vendor lock-in
- **Custom Domain** — User points their domain to their own server IP, Coolify auto-provisions SSL via Let's Encrypt
- **Project Dashboard** — User sees all their generated projects, deploy status, quick links to GitHub repo and live URL

## Users & Roles

| Role       | Can Do                                                                   |
|------------|--------------------------------------------------------------------------|
| Visitor    | View landing page, start wizard, preview (blurred) generation output     |
| Free User  | Complete wizard, see full preview of generated architecture, no export    |
| Paid User  | Export to GitHub, download ZIP, access all generated files                |
| Subscriber | Everything in Paid + Coolify managed hosting + custom domains            |
| Admin      | Manage users, view analytics, edit prompt templates, manage Coolify      |

## Integrations

| Service          | Purpose                                    | Config Location        |
|------------------|--------------------------------------------|------------------------|
| Anthropic API    | AI generation of project architecture      | `config/services.php`  |
| GitHub API       | OAuth login + repo creation + file push    | `config/services.php`  |
| Coolify API      | Managed deploy, DB provisioning, SSL       | `config/services.php`  |
| Stripe           | One-time payment + subscription billing    | `config/services.php`  |
| Redis            | Queue, caching, session                    | `config/database.php`  |

## User Journey (Template-First Flow)

```
Browse Template Library (25 templates) → Select Template
→ Wizard opens PRE-POPULATED with template defaults
→ User customizes (renames models, adds fields, adjusts roles)
→ AI generates scaffold = template foundation + user customizations
→ Preview output → Pay/Subscribe → Export to GitHub → Clone locally
→ Open Claude Code → Agent has full context → Start building

Optional: → Click "Deploy" → Coolify provisions → Live URL in 3 min
```

## Pricing

| Plan       | Price         | Includes                                                        |
|------------|---------------|-----------------------------------------------------------------|
| Free       | $0            | AI generation with generic skeleton, preview all files          |
| Pro        | $29 one-time  | GitHub export, ZIP download, unlimited generations              |
| Pro+       | $12/mo        | All Pro features + 25 premium templates + BYOS deploy automation |

## Business Rules

- **Free users can generate and preview but NOT export.** Preview shows all files with full content — no blurring. Gate is on export
- **Pro ($29 one-time) unlocks export forever.** Unlimited generations with generic skeleton
- **Pro+ ($12/mo) adds premium templates and BYOS deploy.** Access to all 25 industry-specific templates with pre-built migrations, models, seeders. Plus automated deploy to user's own server via Coolify
- **Template + Wizard flow:** User selects template first, wizard opens pre-populated, user customizes, AI generates. Template provides proven foundation (80%), wizard captures user-specific customization (20%)
- **BYOS (Bring Your Own Server):** Draplo never hosts user apps. User provides their Hetzner/DO/Linode/Vultr API key, Draplo provisions and deploys to THEIR server. User owns everything. Cancelling Draplo does not affect running apps
- **Server API keys encrypted at rest.** Never logged, never exposed after initial entry
- **Generated repos are owned by the user.** We never access, modify, or delete their GitHub repos after creation
- **Prompt caching is mandatory.** The system prompt (~3000 tokens) must use Anthropic's prompt caching to keep API costs under $0.25 per generation
- **Coolify resources are limited per plan.** Free hosting tier: 1 app, 1 database. Paid: up to 5 apps, 5 databases. Enforced via Coolify API resource limits
- **Rate limiting on generation:** Max 5 generations per hour per user (prevents AI cost abuse)
- **Static skeleton is versioned.** When we update the Laravel skeleton template, we bump a version number. Each generated project records which skeleton version it used

## What Gets Generated (Output Spec)

For a project described as "SaaS platform for managing dental appointments with patient records, SMS reminders, and online booking":

```
project-name/
├── CLAUDE.md                    ← AI-generated: full context for Claude Code
├── PROJECT.md                   ← AI-generated: what this app is, features, roles, glossary
├── todo.md                      ← AI-generated: phased backlog with checkboxes
├── docker-compose.yml           ← Static: PostgreSQL, Redis
├── .env.example                 ← Static + dynamic: DB config, API key placeholders
├── .claude-reference/
│   ├── architecture.md          ← AI-generated: DB schema, API endpoints, file structure
│   ├── constants.md             ← AI-generated: enums, statuses, types
│   ├── patterns.md              ← AI-generated: code patterns specific to this app
│   ├── decisions.md             ← AI-generated: architectural decisions
│   ├── todo/                    ← Empty (session logs go here)
│   ├── features/                ← Empty (feature docs go here)
│   └── plans/                   ← AI-generated: business plan if requested
├── .claude-screenshots/         ← Empty
├── .claude-ui-reference/
│   ├── screens/                 ← Empty (mockups go here)
│   └── components/              ← Empty
├── .claude-deploy/
│   ├── deploy.sh                ← Static: Hetzner deploy script
│   ├── deploy-initial.sh        ← Static: first-time server setup
│   └── README.md                ← Static: deploy documentation
├── app/                         ← Static: Laravel skeleton
├── database/
│   └── migrations/              ← AI-generated: migration files for core models
├── routes/
│   └── api.php                  ← AI-generated: route stubs for all endpoints
├── tests/                       ← Static: test scaffolding
└── ...                          ← Rest of Laravel 12 skeleton
```

## Glossary

| Term            | Meaning in this project                                                  |
|-----------------|--------------------------------------------------------------------------|
| Generation      | One AI-powered creation of project architecture from wizard input        |
| Scaffold        | The complete output: Laravel skeleton + AI-generated docs + migrations   |
| Wizard          | The step-by-step form where user describes their SaaS idea              |
| Skeleton        | The static Laravel boilerplate files (auth, tenancy, docker, deploy)     |
| Architecture    | The AI-generated project-specific files (schema, endpoints, patterns)    |
| Export          | Pushing generated scaffold to GitHub or downloading as ZIP               |
| Deploy          | Provisioning the app on Coolify with database, SSL, and auto-deploy     |
| Agent-ready     | Optimized for AI coding agents — structured docs, clear context, phased tasks |
| Open Core       | Business model: open source core + paid cloud service                    |
| Self-hosted     | User runs Draplo on their own server (free, AGPL-3.0)               |
| Cloud           | Managed version at draplo.com (paid)                                 |
| Premium prompt  | Enhanced system prompt (cloud-only) that generates richer scaffold output |

## Open Source & Licensing

**License:** AGPL-3.0

**Open Core model:**
- Platform code is fully open source — wizard, AI generation, preview, export, Coolify deploy
- Base system prompts are in the repo (generates good quality scaffolds)
- Self-hosting is free forever with ALL features
- Revenue comes from managed cloud version (convenience) and premium prompts (quality)

**Self-hosting requirements:**
- Any server with 2+ vCPU, 4GB RAM (Hetzner CX22 at €4/mo is enough)
- Anthropic API key (user provides their own — pays Anthropic directly)
- GitHub OAuth app (for login)
- Optional: second server with Coolify for user deploys
- Optional: Stripe for payments (can be disabled for personal/team use)

**Feature flags** in .env control what's enabled:
- `STRIPE_ENABLED=false` → all features free (personal use)
- `COOLIFY_ENABLED=false` → hide deploy feature (generate + export only)
- `GITHUB_ENABLED=false` → ZIP download only
- `PREMIUM_PROMPTS_ENABLED=false` → use base prompts (open source default)
