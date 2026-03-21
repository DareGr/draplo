# Draplo Development

## Current Status

**Latest**: Project initialized — 2026-03-20
**Branch**: `main`
**Next up**: Laravel project setup + wizard UI skeleton

---

## In Progress

_None_

---

## Pending (Backlog)

### Phase 1 — Foundation + Wizard UI (Week 1-2)

- [ ] Laravel 12 project setup
- [ ] Docker Compose (PostgreSQL 16, Redis)
- [ ] Install Sanctum, Horizon, configure .env
- [ ] Migration: `users` (name, email, password, github_id, github_token, stripe_customer_id, plan)
- [ ] Migration: `projects` (user_id, name, slug, description, wizard_data JSON, generation_output JSON, skeleton_version, github_repo_url, coolify_app_id, status, exported_at, deployed_at)
- [ ] Migration: `generations` (project_id, input_hash, prompt_tokens, completion_tokens, cost_usd, model, duration_ms, created_at)
- [ ] Model: User (with relationships, plan helpers)
- [ ] Model: Project (with status enum, JSON casts)
- [ ] Model: Generation (with cost tracking)
- [ ] React wizard: Vite + React 18 setup
- [ ] React wizard: Template Library page (grid of 25 templates with preview, description, category filter)
- [ ] React wizard: Template detail — shown inline on template cards (no modal, direct click-to-wizard flow)
- [ ] React wizard: Step 1 — "Describe your app" (pre-populated from template, user customizes name + description)
- [ ] React wizard: Step 2 — "Who uses it?" (roles pre-populated from template, user can rename/add/remove)
- [ ] React wizard: Step 3 — "Core models" (pre-populated from template with locked + customizable models, user can rename, add fields, add new models)
- [ ] React wizard: Step 4 — "Auth & tenancy" (pre-populated, user adjusts)
- [ ] React wizard: Step 5 — "Integrations" (pre-checked based on template, user toggles)
- [ ] React wizard: Step 6 — "Review & generate" (summary of template + customizations, generate button disabled until Phase 2)
- [ ] Template Library: standalone page with category filter, template grid, "Start from Scratch" card
- [ ] Template Library: clicking template → POST /api/wizard/projects → redirects to wizard pre-populated
- [ ] Wizard API: GET /api/templates (list templates for library grid)
- [ ] Wizard API: POST /api/wizard/projects (create draft project, optionally from template)
- [ ] Wizard API: PUT /api/wizard/projects/{id} (save wizard state per step)
- [ ] Wizard API: GET /api/wizard/projects/{id} (load project for resuming)
- [ ] Wizard API: DELETE /api/projects/{id} (delete project)
- [ ] Wizard state persistence (save to projects.wizard_data JSON, resume incomplete wizards)
- [ ] Write tests: wizard save/load, user creation

### Phase 2 — AI Generation Engine + Launch Templates (Week 3-4)

- [ ] Template storage structure: `storage/app/templates/{slug}/` with template.json, wizard-defaults.json, prompt-context.md
- [ ] Template loader service: read template data, merge with wizard customizations
- [ ] Build Template #1: Booking & Reservation Platform (our most universal template)
- [ ] Build Template #2: CRM (Customer Relationship Management)
- [ ] Build Template #3: Invoice & Billing Platform
- [ ] Build Template #4: Restaurant & Café Management (dogfood — our QRMeni)
- [ ] Build Template #5: Project Management Tool
- [ ] Each template: tested migrations, models with relationships, realistic seeders, API route stubs

- [ ] Wizard API: POST /api/wizard/projects/{id}/suggest (AI suggests models from description — deferred from Phase 1)
- [ ] AnthropicService — wrapper for Claude API with prompt caching
- [ ] System prompt v1 — master prompt with TWO layers:
  - [ ] Layer 1 (cached): Base instructions for generating all output files + Laravel patterns + quality rules
  - [ ] Layer 2 (per-template, cached): Template-specific context from `prompt-context.md` (models, relationships, patterns specific to that template type)
  - [ ] Layer 3 (per-user, unique): User's wizard customizations (renamed models, added fields, specific description)
- [ ] System prompt: instructions for CLAUDE.md generation (project context, rules, stack info)
- [ ] System prompt: instructions for PROJECT.md generation (features, roles, glossary)
- [ ] System prompt: instructions for todo.md generation (phased backlog with checkboxes)
- [ ] System prompt: instructions for architecture.md generation (DB schema tables, API endpoints, file tree)
- [ ] System prompt: instructions for constants.md generation (enums, statuses, types)
- [ ] System prompt: instructions for patterns.md generation (code patterns, tenant scoping, etc.)
- [ ] System prompt: instructions for decisions.md generation (architectural decisions)
- [ ] System prompt: instructions for migration file generation (valid Laravel migration PHP code)
- [ ] System prompt: instructions for api.php route stub generation
- [ ] GenerationService — orchestrates: build prompt → call API → parse output → store
- [ ] Output parser — split AI response into individual files (use XML tags or markdown headers)
- [ ] Generation cost tracking — log tokens used, cost in USD, duration
- [ ] Input hash caching — same wizard input = return cached output (skip API call)
- [ ] Rate limiting — max 5 generations/hour per user
- [ ] Quality validation — verify generated migrations are valid PHP (basic syntax check)
- [ ] Test with 5+ diverse project descriptions:
  - [ ] Test: "Dental appointment SaaS with patient records and SMS reminders"
  - [ ] Test: "Multi-tenant project management tool like Basecamp"
  - [ ] Test: "E-commerce marketplace for handmade goods"
  - [ ] Test: "Restaurant digital menu with QR codes" (our own QRMeni!)
  - [ ] Test: "Freelancer invoicing and time tracking tool"
- [ ] Iterate on system prompt based on test results
- [ ] Write tests: generation flow, caching, rate limiting, output parsing

### Phase 3 — Preview + Export (Week 5-6)

- [ ] Preview UI: code viewer with syntax highlighting (Monaco editor or Prism.js)
- [ ] Preview UI: file tree sidebar showing all generated files
- [ ] Preview UI: tab navigation between files
- [ ] Preview UI: "Regenerate" button (re-run AI with same inputs)
- [ ] Preview UI: inline editing (user can tweak generated content before export)
- [ ] GitHub OAuth integration (login + repo permissions)
- [ ] GitHubService — create private repo, push files via GitHub API (Contents API or Git Data API)
- [ ] Export flow: user clicks "Push to GitHub" → creates repo → pushes all files → shows repo URL
- [ ] ZIP download alternative — generate .zip with all project files for users who don't want GitHub
- [ ] Stripe integration — one-time payment ($29-49) to unlock export
- [ ] Stripe webhook handler — mark user as paid on successful charge
- [ ] Free preview gate — non-paying users see all content but "Export" button triggers payment
- [ ] Post-export: show "What's next" instructions (clone, docker-compose up, claude, start building)
- [ ] Write tests: GitHub repo creation, Stripe payment flow, ZIP generation

### Phase 4 — BYOS Deploy Integration (Week 7-9)

- [ ] ServerProviderService — abstract interface for Hetzner/DO/Linode/Vultr APIs
- [ ] HetznerProvider — create VPS, get IP, SSH access, install Coolify
- [ ] DigitalOceanProvider — same interface
- [ ] LinodeProvider — same interface
- [ ] VultrProvider — same interface
- [ ] Migration: `server_connections` (user_id, provider, encrypted_api_key, server_id, server_ip, coolify_url, status)
- [ ] Server setup flow: user enters API key → Draplo provisions VPS → installs Coolify via SSH → stores connection
- [ ] Encrypt server API keys at rest (Laravel encrypt()) — NEVER log, NEVER expose after entry
- [ ] CoolifyService — wrapper for Coolify REST API (on user's server, not ours)
- [ ] Coolify: create app from GitHub repo
- [ ] Coolify: create PostgreSQL database
- [ ] Coolify: set env vars from generated .env
- [ ] Coolify: trigger build + deploy
- [ ] Coolify: retrieve deploy status + live URL
- [ ] Coolify: configure custom domain + auto SSL
- [ ] Deploy UI: server connection setup page (choose provider, enter API key)
- [ ] Deploy UI: "Deploy" button on project (only if server connected)
- [ ] Deploy UI: real-time deploy progress with steps (match deployment_status design screen)
- [ ] Deploy UI: resource meters (CPU, RAM, Storage — from Coolify API)
- [ ] Deploy UI: terminal-style build log (live_build.log component from design)
- [ ] Deploy UI: show live URL + SSL status when complete
- [ ] Stripe Pro+ subscription gates BYOS features
- [ ] Auto-deploy webhook — new git push → Coolify redeploys
- [ ] Write tests: provider API mocks, server provisioning, Coolify integration

### Phase 5 — Dashboard + Landing Page + Open Source + Launch (Week 10-12)

**Design System Implementation:**
- [ ] Tailwind config: import full color token set from DESIGN.md
- [ ] Font loading: Inter, Space Grotesk (Google Fonts) + Berkeley Mono (self-hosted or fallback)
- [ ] Material Symbols Outlined icon integration (NO emoji in UI)
- [ ] Base components following "Monolithic Architect" design system:
  - [ ] Button variants (primary gradient, secondary ghost-border, tertiary/ghost)
  - [ ] Input fields (monospace text, ghost border, primary glow on focus)
  - [ ] Cards (tonal layering, no solid borders, hover state shift)
  - [ ] Chips/badges (status dots: green/amber/red, monospace labels)
  - [ ] Terminal component (darkest bg, Berkeley Mono, ghost border)
  - [ ] Glass panels (semi-transparent + backdrop-blur for floating elements)

**Landing Page (Blade, not React — for SEO):**
- [ ] Three.js hero element: interactive 3D scaffold visualization
  - [ ] Geometric blocks assembling into architecture (not organic/blobby)
  - [ ] Primary (#c0c1ff) and primary-container (#8083ff) glow colors
  - [ ] Mouse-responsive (subtle camera/element movement)
  - [ ] 60fps performance target, graceful fallback on mobile
- [ ] Hero section: headline, subheadline, Browse Templates + View on GitHub CTAs
- [ ] Tech stack bar: Laravel, PostgreSQL, React, Tailwind, Claude, Coolify logos
- [ ] "Built for speed, refined by intelligence" process section (4 steps)
- [ ] Featured Blueprints section (6 template cards)
- [ ] "Built for Claude & Cursor Agents" section (.claude-reference/ preview + agent chat mockup)
- [ ] Pricing section (3 tiers matching design screen)
- [ ] Open source section ("Fully open source. AGPL-3.0." + GitHub stars CTA)
- [ ] Footer with links
- [ ] Match landing page design from `.claude-ui-reference/screens/draplo_landing_page/`

**Dashboard (React SPA):**
- [ ] Dashboard: welcome + system status, stat cards (projects, deployed, generations, plan)
- [ ] Dashboard: recent deployments list with status badges
- [ ] Dashboard: AI architect terminal log component (bottom of dashboard)
- [ ] Projects list: view/deploy/manage per project
- [ ] Account settings: plan, billing, server connections, GitHub connection
- [ ] Match dashboard design from `.claude-ui-reference/screens/user_dashboard/`

**Admin:**
- [ ] Admin dashboard: user count, total generations, revenue, API costs
- [ ] Admin: prompt template editor (edit system prompt without redeploying)

**Open Source Prep:**
- [ ] Feature flags: all BYOS providers, STRIPE, GITHUB, PREMIUM_TEMPLATES, THREEJS_HERO
- [ ] Conditional UI: hide features based on flags
- [ ] README.md: comprehensive self-hosting guide
- [ ] README.md: feature flag documentation
- [ ] LICENSE: AGPL-3.0
- [ ] CONTRIBUTING.md
- [ ] .env.example with all flags commented
- [ ] SEO: meta tags, OG images, sitemap

**Launch:**
- [ ] Deploy Draplo to production (Hetzner + Coolify)
- [ ] Beta test: generate 3 real projects (QRMeni, booking system, invoice tool)
- [ ] Soft launch: Twitter/X, r/laravel, r/SideProject, Indie Hackers, Hacker News
- [ ] GitHub repo: topics, description, screenshots
- [ ] 🎉 LAUNCH

### Post-MVP (Month 4+)

**Template Rollout:**
- [ ] Month 2: +5 templates (E-commerce, Helpdesk, CMS, School Management, Subscription)
- [ ] Month 3-4: +5 templates (Marketplace, Field Service, LMS, Directory, Event Management)
- [ ] Month 5-6: +5 templates (Patient Records, Job Board, Newsletter, Monitoring, API-as-a-Service)
- [ ] Month 7+: +5 templates (Inventory, Fleet, Fitness, Property Rental, Analytics Dashboard)

**Platform Features:**
- [ ] Import existing project — point to GitHub repo, AI generates .claude-reference docs
- [ ] Template marketplace — users share/sell custom templates (20-30% commission)
- [ ] Multi-stack support: Django + Next.js templates
- [ ] Team/organization accounts with shared projects
- [ ] AI-generated UI mockups (HTML files in .claude-ui-reference/)
- [ ] AI-generated Pest test stubs
- [ ] "Refine" mode: re-run AI on specific files with additional instructions
- [ ] Coolify auto-scaling: monitor resource usage, suggest server upgrades
- [ ] Referral program: users get credit for referring other developers
- [ ] API access: generate projects programmatically (for agencies)
- [ ] VS Code / Cursor extension: one-click scaffold from IDE

---

## Session Logs

| Date | Summary |
|------|---------|

---

## Completed Features (summary)

_None yet_

---

## Ideas (Quick Capture)

- 2026-03-20: Consider generating a basic Dockerfile per project (not just docker-compose) for Coolify compatibility
- 2026-03-20: Could offer "import existing project" — user points to GitHub repo, AI analyzes code and generates missing .claude-reference docs
- 2026-03-20: Prompt versioning — track which prompt version generated each project, allow re-generation with updated prompts
- 2026-03-20: Community prompt library — users share system prompts that produce great results for specific niches

---

## Revenue Targets

| Metric                | Month 1      | Month 3      | Month 6       |
|-----------------------|--------------|--------------|---------------|
| Registered users      | 50           | 300          | 1,000+        |
| Pro purchases ($29)   | 10           | 40           | 150+          |
| Pro+ subscribers ($12)| 0            | 15           | 60+           |
| Templates available   | 5            | 15           | 25            |
| One-time revenue      | $290         | $1,160       | $4,350        |
| Subscription MRR      | $0           | $180         | $720+         |
| API costs             | ~$5          | ~$20         | ~$75          |
| Infrastructure costs  | ~€15/mo      | ~€25/mo      | ~€60/mo       |

---

## Cost Structure

| Item                      | Cost                     | Notes                          |
|---------------------------|--------------------------|--------------------------------|
| Hetzner VPS (Draplo app)  | €5-10/mo                 | App hosting                    |
| Anthropic API             | ~$0.15-0.25/generation   | Sonnet 4.6 with prompt caching |
| GitHub API                | Free                     | 5000 req/hr authenticated      |
| Stripe fees               | 2.9% + $0.30/transaction | Standard pricing               |
| Domain (draplo.com)       | ~$12/year                |                                |

**Note:** No managed Coolify server needed — BYOS model means users pay their own server costs. Our infrastructure is ONLY the Draplo platform itself.

---

## Maintenance Rules

- **In Progress**: Max 3 items
- **Backlog**: Keep prioritized by phase
- **Session Logs**: New file per session in `.claude-reference/todo/YYYY-MM-DD.md`
- **Keep this file lean** — detailed info goes in session logs and feature docs
