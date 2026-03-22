# Draplo — Your next SaaS, architected by AI

> Draft it. Deploy it. Open source.

Draplo is an open-source platform where developers describe their SaaS idea through a guided wizard, and AI generates a complete, deployment-ready Laravel project scaffold optimized for AI coding agents (Claude Code, Cursor, Aider).

**This is NOT a no-code builder.** Draplo generates a professional developer scaffold — the same quality output a senior Laravel architect would produce in 2 weeks of planning. You still write the actual application code.

## Features

- **Guided Wizard** — 6-step form with 25 industry templates pre-populated with models, roles, and integrations
- **AI Generation** — Claude (Anthropic) or Gemini (Google) generates 7+ project files: CLAUDE.md, PROJECT.md, todo.md, architecture, constants, patterns, decisions, migrations, routes
- **Code Preview** — Syntax-highlighted preview with CodeMirror 6, inline editing before export
- **GitHub Export** — Push to a private GitHub repo via Git Data API
- **ZIP Download** — Download your scaffold as a ZIP file
- **BYOS Deploy** — Bring Your Own Server: auto-provision Hetzner VPS or connect any server with Coolify

## Quick Start

### Prerequisites
- Docker & Docker Compose
- PHP 8.3+ (for running artisan locally)
- Node.js 20+ & npm
- Composer

### Setup

```bash
git clone https://github.com/yourusername/draplo.git
cd draplo
cp .env.example .env

# Start PostgreSQL + Redis
docker-compose up -d

# Install dependencies
composer install
npm install

# Setup database
php artisan migrate --seed

# Start development servers
npm run dev &
php artisan serve

# Visit http://localhost:8000
```

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 + PHP 8.3 |
| Frontend | React 18 (SPA) + Blade (landing) |
| Styling | Tailwind CSS 4 |
| Database | PostgreSQL 16 |
| Cache/Queue | Redis 7 + Laravel Horizon |
| AI | Anthropic Claude / Google Gemini (configurable) |
| Auth | GitHub OAuth via Socialite |
| Export | GitHub API (Git Data API) |
| Deploy | Coolify (on user's server) |
| 3D Visual | Three.js (landing page hero) |

## Feature Flags

All features can be enabled/disabled via `.env`:

| Flag | Default | Description |
|------|---------|-------------|
| `STRIPE_ENABLED` | `true` | Enable Stripe payments (not implemented yet) |
| `COOLIFY_ENABLED` | `true` | Enable BYOS deploy features |
| `GITHUB_ENABLED` | `true` | Enable GitHub OAuth + export |
| `PREMIUM_TEMPLATES_ENABLED` | `true` | Enable premium template access |
| `THREEJS_HERO_ENABLED` | `true` | Enable Three.js 3D hero on landing |
| `BYOS_HETZNER_ENABLED` | `true` | Enable Hetzner auto-provisioning |

Disabling `STRIPE_ENABLED` makes all features free (perfect for self-hosting).

## AI Provider Configuration

Draplo supports two AI providers. Configure via `.env` or admin panel:

```env
AI_PROVIDER=anthropic          # or 'gemini'
AI_MODEL=claude-sonnet-4-6     # or 'gemini-2.5-pro'
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=AIza...
```

Admin can switch providers at runtime via `GET/PUT /api/admin/settings`.

## Self-Hosting Guide

### Requirements
- Server with 2+ vCPU, 4GB RAM (Hetzner CX22 at ~$4/mo is enough)
- Your own Anthropic or Gemini API key
- GitHub OAuth app (for login)
- Optional: Stripe account (for payments)

### GitHub OAuth Setup
1. Go to GitHub Settings > Developer Settings > OAuth Apps
2. Create new: Homepage URL = your domain, Callback URL = `{domain}/auth/github/callback`
3. Copy Client ID and Client Secret to `.env`

### Production Deployment
1. Clone repo to your server
2. Configure `.env` with production values
3. `composer install --no-dev`
4. `npm run build`
5. `php artisan migrate --seed`
6. Configure nginx/Caddy to serve the app
7. Set up Horizon for queue processing: `php artisan horizon`

## Architecture

```
draplo/
├── app/
│   ├── Services/AI/         — AI provider interface + implementations
│   ├── Services/Deploy/     — Hetzner + Coolify services
│   ├── Services/            — Generation, Output Parser, Settings, Templates
│   ├── Http/Controllers/    — API controllers
│   ├── Jobs/                — Queued generation + deploy jobs
│   └── Models/              — User, Project, Generation, ServerConnection
├── resources/
│   ├── js/                  — React SPA (wizard, preview, dashboard)
│   └── views/               — Blade (landing page)
├── storage/app/templates/   — 25 template data files
└── tests/                   — 100+ Pest PHP tests
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup and guidelines.

## License

[AGPL-3.0](LICENSE) — Fully open source. Self-host for free. Forever.

Built by developers, for developers. If you find Draplo useful, consider starring the repo.
