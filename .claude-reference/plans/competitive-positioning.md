# Draplo — Competitive Positioning & Advantages

## What Draplo Is NOT

Draplo is NOT a deployment platform. It is NOT competing with Vercel, Netlify, or Railway.

**Vercel/Netlify/Railway** = You give them finished code, they host it. Zero help with planning, architecture, or documentation.

**Draplo** = You describe your idea, AI generates the complete architecture + documentation + migrations + deploy config, optimized for AI coding agents. Deployment is a bonus feature, not the core product.

**The real competitor** is the manual process: `laravel new project` followed by 1-2 weeks of planning schemas, writing migrations, designing API endpoints, creating documentation. Draplo replaces that with 3 minutes.

## Core Differentiators

### 1. Agent-Ready Output (UNIQUE — nobody else does this)

No platform in the world generates CLAUDE.md, .claude-reference/ structure, patterns.md, decisions.md — files specifically optimized for AI coding agents. Claude Code, Cursor, and Aider have exploded in 2025-2026 with millions of developer users, but every single one of them wastes time on context setup. Draplo solves this exact problem.

Developer clones the repo, opens terminal, types `claude`, and the agent IMMEDIATELY knows what to build because CLAUDE.md and todo.md are already there with full context, rules, and phased tasks.

### 2. Laravel-Specific, Not Generic

Vercel is optimized for Next.js/React. Railway is generic (Docker anything). Neither is optimized for the Laravel ecosystem. Draplo generates scaffolds using the exact Laravel packages that Laravel developers use daily:

- Multi-tenancy: stancl/tenancy (single-DB mode)
- Auth: Laravel Sanctum with role-based access
- Queues: Laravel Horizon with Redis
- WebSockets: Laravel Reverb
- Storage: MinIO (S3-compatible)
- Search: Laravel Scout + Meilisearch
- Deploy: Hetzner + Nginx + Cloudflare

A Laravel developer gets a scaffold that works in THEIR stack on day one. No adapting generic boilerplate.

### 3. Architecture BEFORE Code, Not Just Deployment AFTER Code

Vercel and Railway help only when you have finished code. Draplo helps from zero — from an idea to a deployed application. A developer who only has "I want a SaaS for dental appointments" has nothing to deploy on Vercel. But they can come to Draplo, describe the idea, and in 3 minutes have a repo with database schema, API endpoints, migration files, and a live URL.

From zero to deployed app in one flow.

### 4. Coolify = Zero Vendor Lock-In

Vercel locks you into their ecosystem. Migrating away from Vercel is painful. Draplo uses BYOS (Bring Your Own Server) — the user provides their own Hetzner, DigitalOcean, Linode, or Vultr server. Draplo automates Coolify installation and deployment, but the user owns their server completely. If Draplo shuts down tomorrow, the user's application keeps running because it's on their own infrastructure. Zero vendor lock-in is not a marketing phrase — it's architecturally enforced.

### 5. Drastically Cheaper

| Platform          | Monthly Cost (typical SaaS)  |
|-------------------|------------------------------|
| Vercel Pro        | $20/mo per team member       |
| Railway           | $20-50/mo (usage-based)      |
| Render            | $25-75/mo                    |
| Heroku            | $25-50/mo                    |
| **Draplo**       | **$29 one-time + $12/mo**    |
| **Self-hosted**   | **$0 + own server (~€5/mo)** |

For indie developers and bootstrapped startups counting every dollar, this is a massive difference.

### 6. Open Source — Transparency & Trust

Vercel, Railway, Render — all closed source. You don't know what they do with your code, your env variables, your data. Draplo is AGPL open source — developers can read every line of code, verify there's no telemetry, and self-host for complete control. For privacy-conscious developers, this is the deciding factor.

## Why YOU (the creator) Would Use This Over Vercel/Railway

1. You build Laravel SaaS apps — Vercel is useless for your stack
2. Railway can host Laravel but gives zero help with planning
3. Every new idea (QRMeni, event platform, invoice tool) that takes hours to architect manually can be scaffolded in 3 minutes
4. From idea to running application in 10 minutes: wizard (3 min) → GitHub push (1 min) → Coolify deploy (3 min) → open Claude Code with full context → start building
5. You eat your own dog food — every project you build validates and improves the platform

## Future Features to Strengthen Positioning

### Import Existing Project (HIGH VALUE)
Developer has existing Laravel repo but no CLAUDE.md or structured documentation. Point Draplo at the repo, AI analyzes the code and generates .claude-reference/ folder with architecture docs, patterns, constants. Millions of existing Laravel projects need this. This alone could be a standalone product.

### Template Marketplace
Users share and sell custom skeleton templates:
- "E-commerce starter" with Stripe, inventory, order management — $49
- "Multi-tenant CRM" with contacts, deals, pipeline — $39
- "API-only starter" with versioned endpoints, rate limiting — $29

Draplo takes 20% commission. This transforms the platform from a tool into an ecosystem.

### AI-Generated Pest Tests
Generate test stubs for every endpoint alongside the architecture. Developer clones, runs `php artisan test`, sees failing tests that tell them exactly what to implement. Test-driven development out of the box. Premium feature for cloud version.

### AI-Generated UI Mockups
Generate HTML mockup files in .claude-ui-reference/screens/. Developer has visual reference for every screen before writing a line of frontend code. Premium feature for cloud version.

### One-Click Database Seeder
Generate realistic seed data alongside migrations. Developer clones → migrates → seeds → instantly has a working app with demo data for testing. No more manually creating test records.

### Monitoring Dashboard
For deployed applications, show basic metrics: uptime, response time, error rate, database size. User doesn't need to set up separate monitoring — it's built in to the Draplo dashboard.

### Multi-Environment
Staging + production per project. Push to `main` → production. Push to `develop` → staging. Two Coolify environments per project. Essential for professional workflows.

### Team Collaboration
Invite team members to a project. Both see dashboard, both can deploy. Shared generation history. Useful for small teams and agencies.

### Refine Mode
After initial generation, user can re-run AI on specific files with additional instructions: "Add soft deletes to all models" or "Change auth from Sanctum to Passport" or "Add Stripe integration to the architecture." Iterative refinement without regenerating everything.

## One-Sentence Pitch

"Vercel deploys your finished code. Draplo generates your architecture, documentation, and infrastructure from scratch — optimized for AI agents — and deploys it to YOUR server. From idea to live URL in 10 minutes. Your server, your code, your data."

## Target Audience (in priority order)

1. **Solo developers using Claude Code/Cursor** who start new Laravel projects frequently
2. **Indie hackers / bootstrappers** who want to validate ideas fast without spending weeks on boilerplate
3. **Freelancers / agencies** who spin up client projects and want standardized scaffolding
4. **Teams** who want consistent project structure and documentation across all projects
5. **Developers learning Laravel** who want to see how a senior architect would structure an app
