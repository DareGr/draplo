# Draplo — Manual Testing Guide

Complete end-to-end testing checklist for all features. Follow sections in order — each builds on the previous.

---

## Prerequisites

### 1. Environment Setup

```bash
cd /path/to/draplo
cp .env.example .env
docker-compose up -d
composer install
npm install
php artisan migrate:fresh --seed
```

### 2. Configure .env

```env
# Required for full testing:
AI_PROVIDER=anthropic
AI_MODEL=claude-sonnet-4-6
ANTHROPIC_API_KEY=sk-ant-your-key-here
# OR
# AI_PROVIDER=gemini
# AI_MODEL=gemini-2.5-pro
# GEMINI_API_KEY=AIza-your-key-here

# GitHub OAuth (for login + export):
GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-client-secret
GITHUB_REDIRECT_URL=http://localhost:8000/auth/github/callback

# Feature Flags (all true by default):
STRIPE_ENABLED=true
COOLIFY_ENABLED=true
GITHUB_ENABLED=true
PREMIUM_TEMPLATES_ENABLED=true
THREEJS_HERO_ENABLED=true
BYOS_HETZNER_ENABLED=true
```

### 3. Start Services

```bash
# Terminal 1: Vite dev server
npm run dev

# Terminal 2: Laravel server
php artisan serve

# Terminal 3: Queue worker (REQUIRED for generation + deploy)
php artisan horizon
```

### 4. Access

- Landing page: http://localhost:8000
- SPA: http://localhost:8000/templates
- Dev login (no GitHub OAuth needed): http://localhost:8000/dev/login

---

## Test 1: Landing Page

**URL:** http://localhost:8000

- [ ] Page loads with dark theme, no layout shifts
- [ ] **Three.js Hero:** 3D geometric blocks visible, animating (assembling into scaffold)
- [ ] Hero: mouse movement shifts camera slightly (parallax effect)
- [ ] Hero: headline "Your next SaaS, architected by AI" visible
- [ ] Hero: "Browse Templates" button links to /templates
- [ ] Hero: "View on GitHub" button has correct link
- [ ] **Tech Stack Bar:** 6 icons with labels (Laravel, PostgreSQL, React, Tailwind, Claude, Coolify)
- [ ] **How It Works:** 4 steps displayed (Describe, Generate, Preview, Deploy)
- [ ] **Featured Blueprints:** 6 template cards rendered correctly
- [ ] "See All 25 Templates" link works
- [ ] **Agent-Ready Section:** terminal card shows .claude-reference/ folder structure
- [ ] **Pricing:** 3 tiers displayed (Free, Pro, Pro+)
- [ ] **Open Source Section:** AGPL-3.0 text, GitHub CTA
- [ ] **Footer:** copyright, navigation links
- [ ] **SEO:** View page source — check `<title>`, `<meta description>`, `og:` tags present
- [ ] **Sitemap:** Visit http://localhost:8000/sitemap.xml — valid XML
- [ ] **Robots.txt:** Visit http://localhost:8000/robots.txt — correct content
- [ ] **Mobile:** Resize browser to 375px width — layout responsive, no horizontal scroll
- [ ] **Three.js Fallback:** Set `THREEJS_HERO_ENABLED=false` in .env, clear config cache (`php artisan config:clear`), reload — gradient fallback shown instead of 3D

---

## Test 2: Authentication

### Option A: Dev-Mode Login (local development only)

> Works only when `APP_ENV=local`. Quick way to test without GitHub OAuth configured.

- [ ] Visit http://localhost:8000/dev/login — returns JSON with `token` and `user`
- [ ] Copy token, add to localStorage: `localStorage.setItem('auth_token', 'TOKEN_HERE')`
- [ ] Visit http://localhost:8000/templates — page loads without "Initializing..." stuck
- [ ] Visit http://localhost:8000/api/auth/me (with token in header) — returns dev user
- [ ] **Note:** Dev user is admin (`is_admin: true`) — can access /admin

### Option B: GitHub OAuth (production flow)

> This is the ONLY auth method in production. Must be tested before deploying.

**Setup (one-time):**
1. Go to https://github.com/settings/developers → "New OAuth App"
2. Application name: `Draplo` (or `Draplo Local` for testing)
3. Homepage URL: `http://localhost:8000` (or your production domain)
4. Authorization callback URL: `http://localhost:8000/auth/github/callback`
5. Register → copy Client ID + generate Client Secret
6. Add to `.env`:
   ```env
   GITHUB_CLIENT_ID=your-client-id
   GITHUB_CLIENT_SECRET=your-client-secret
   GITHUB_REDIRECT_URL=http://localhost:8000/auth/github/callback
   ```
7. Clear config: `php artisan config:clear`

**Test flow:**

- [ ] Visit http://localhost:8000 (landing page)
- [ ] Click "Sign In" button in top navigation
- [ ] Redirected to GitHub authorization page (`github.com/login/oauth/authorize?...`)
- [ ] GitHub shows: "Draplo wants to access your account" with `repo` and `user:email` scopes
- [ ] Click "Authorize"
- [ ] Redirected to http://localhost:8000/auth/callback#token=...
- [ ] Automatically redirected to /templates (or /dashboard if returning user)
- [ ] Token stored in localStorage (check: browser DevTools → Application → Local Storage → `auth_token`)

**Verify user creation:**

- [ ] Visit http://localhost:8000/api/auth/me (add `Authorization: Bearer {token}` header)
- [ ] Response contains: `name`, `email`, `github_id`, `github_username`, `avatar_url`, `plan: "free"`
- [ ] Check database: `php artisan tinker --execute="echo App\Models\User::where('github_username', 'YOUR_USERNAME')->first()->toJson();"` — user exists with GitHub data

**Test returning user (login again):**

- [ ] Log out: clear localStorage (`localStorage.removeItem('auth_token')`)
- [ ] Visit /auth/github again → GitHub skips authorization (already authorized) → instant redirect back
- [ ] User is logged in, same account (not duplicated)
- [ ] Token is refreshed (new token in localStorage)

**Test token persistence:**

- [ ] Close browser tab, open new tab → visit http://localhost:8000/templates
- [ ] Still logged in (token persisted in localStorage)
- [ ] Hard refresh (Ctrl+Shift+R) → still logged in

**Test unauthorized access:**

- [ ] Clear localStorage token
- [ ] Visit http://localhost:8000/api/projects → 401 Unauthorized
- [ ] Visit http://localhost:8000/api/auth/me → 401 Unauthorized
- [ ] SPA shows "Initializing..." (dev mode auto-logs in, production would redirect to login)

**Test dev-mode blocked in production:**

- [ ] Set `APP_ENV=production` in `.env`
- [ ] `php artisan config:clear`
- [ ] Visit http://localhost:8000/dev/login → 403 Forbidden
- [ ] Revert: set `APP_ENV=local`, `php artisan config:clear`

### Auth Flow Summary

```
Production:  Landing Page → "Sign In" → GitHub OAuth → /auth/callback#token → SPA
Local Dev:   SPA boot → ensureAuth() → /dev/login → auto-token → SPA
```

---

## Test 3: Template Library

**URL:** http://localhost:8000/templates

- [ ] Page shows "Template Library" heading with eyebrow text
- [ ] **25 template cards** rendered + "Start from Scratch" card
- [ ] Category filter bar visible: All, Operations, Sales, Content, etc.
- [ ] Click "Operations" filter — only operations templates shown
- [ ] Click "All" — all templates shown again
- [ ] **Booking Platform** card: shows icon, "Operations" badge, description, "8 Models", "Medium" complexity
- [ ] **Available templates** (5): Booking, CRM, Invoice, Restaurant, Project Mgmt — these are clickable
- [ ] **Unavailable templates** (20): clicking shows "Coming soon" toast
- [ ] Click "Start from Scratch" — creates project, redirects to wizard with empty fields
- [ ] Click "Booking Platform" — creates project, redirects to wizard with pre-populated data

---

## Test 4: Wizard (6 Steps)

**URL:** http://localhost:8000/wizard/{projectId}

> After clicking a template from the library

### Step 1 — Describe Your App

- [ ] Header shows "Template: Booking Platform → Step 1 of 6"
- [ ] `edit_note` Material Symbol icon (not emoji)
- [ ] **Name** field: empty (user fills in), placeholder visible
- [ ] **Description** field: pre-populated from template ("Online booking and appointment management platform")
- [ ] **Problem** field: pre-populated from template
- [ ] Type a name: "DentBook"
- [ ] Click "Next" — saves and advances to Step 2
- [ ] Bottom bar shows: "Next: Who Uses It?" with arrow

### Step 2 — Who Uses It?

- [ ] `group` icon in heading
- [ ] **App Type:** "B2B SaaS" pill is pre-selected (from template)
- [ ] Click different app type — selection changes
- [ ] **Roles:** 3 roles pre-populated (admin, provider, client)
- [ ] Admin role: shows lock icon, cannot be deleted or renamed
- [ ] Provider role: can be renamed, can be deleted
- [ ] Click "Add Role" — new empty role appears
- [ ] Fill in role name and description
- [ ] Delete the new role — removed from list
- [ ] Click "Next" — saves and advances

### Step 3 — Core Models

- [ ] `schema` icon in heading (NOT emoji)
- [ ] **8 models** pre-populated from Booking Platform template
- [ ] **Tenant model:** shows "LOCKED" badge, cannot be deleted, name not editable
- [ ] **Service model:** name is editable, has fields (name, duration_minutes, price, etc.)
- [ ] **Field chips:** show type indicators — FK (purple tint), T (indigo), # (primary), $ (green), ? (yellow)
- [ ] **Appointment model:** FK fields (client_id, provider_id, service_id) have purple FK badge
- [ ] Click "Add field" on a model — inline form appears with name input + type dropdown
- [ ] Add a field "notes" with type "text" — chip appears
- [ ] Remove a field — chip disappears (hover to see X button)
- [ ] Click "Add New Core Model" — empty model card appears
- [ ] **Relationships widget** (desktop only, right sidebar): shows auto-detected belongsTo relationships from FK fields
- [ ] Delete a non-locked model — card removed
- [ ] Click "Next" — saves and advances

### Step 4 — Auth & Tenancy

- [ ] `shield` icon in heading
- [ ] **Multi-tenancy toggle:** ON (pre-populated from template)
- [ ] Toggle off and on — state changes
- [ ] **Auth method:** shows "Laravel Sanctum" as selected (only option)
- [ ] **Guest access toggle:** ON with description visible
- [ ] Toggle guest access off — description field hides
- [ ] Toggle back on — description reappears
- [ ] Click "Next"

### Step 5 — Integrations

- [ ] `extension` icon in heading
- [ ] **7 integration cards** in 2-column grid
- [ ] **Pre-checked** (from template): SMS, Stripe, Email — toggles are ON
- [ ] Toggle an integration off and on
- [ ] **Notes textarea** at bottom — can type additional notes
- [ ] Click "Next"

### Step 6 — Review & Generate

- [ ] `checklist` icon in heading
- [ ] **5 expandable sections:** App Description, Users & Roles, Core Models, Auth & Tenancy, Integrations
- [ ] Each section shows summary of entered data
- [ ] Click expand/collapse — sections toggle
- [ ] **"Edit" link** on each section — clicking navigates back to that step
- [ ] Navigate back to Step 3, make a change, navigate forward to Step 6 — change reflected
- [ ] **"Generate Scaffold" button** — active (primary gradient)

### Step Navigation

- [ ] "Back" button navigates to previous step
- [ ] "Save Draft" saves current step without advancing
- [ ] Step progress shows in sidebar widget
- [ ] Navigate away and back (via /projects) — wizard state is preserved (resume from where you left off)

---

## Test 5: AI Generation

> Requires ANTHROPIC_API_KEY or GEMINI_API_KEY configured and `php artisan horizon` running

- [ ] On Step 6 (Review), click "Generate Scaffold"
- [ ] Button shows "Generating..." with loading spinner
- [ ] After 15-30 seconds, redirected to Preview page
- [ ] If generation fails, error toast shown, button becomes active again

### Test with different provider

- [ ] Visit /admin, change AI provider to "gemini" (if you have a Gemini key)
- [ ] Generate another project — should use Gemini
- [ ] Check generation info panel — shows correct provider and model

### Test rate limiting

- [ ] Generate 5 projects rapidly (within 1 hour)
- [ ] 6th attempt should return 429 "Rate limit exceeded"

### Test caching

- [ ] Create two projects with identical wizard data
- [ ] Generate the first — API call made
- [ ] Generate the second — should be instant (cache hit, no API call)
- [ ] Check generation info — second shows "cached: true"

---

## Test 6: Preview

**URL:** http://localhost:8000/projects/{id}/preview

> Requires a generated project

- [ ] **File tree (left panel):** shows all generated files in nested directory structure
- [ ] Directories are collapsible (click to toggle)
- [ ] File icons vary by type (.md, .php, .json)
- [ ] File sizes shown in KB
- [ ] Click a file — content loads in code viewer

- [ ] **Code viewer (center):** syntax highlighted content
- [ ] PHP files: PHP syntax highlighting
- [ ] Markdown files: markdown highlighting
- [ ] Line numbers visible
- [ ] Search with Ctrl+F works

- [ ] **Tabs:** clicking files opens them in tabs
- [ ] Maximum 5 tabs — opening 6th closes oldest
- [ ] Click tab to switch between files
- [ ] Close tab with X button

- [ ] **Edit mode:**
- [ ] Click "Edit" toggle in toolbar — mode switches to editable
- [ ] Edit content in the code viewer
- [ ] "Save Changes" button appears
- [ ] Click Save — content saved to API
- [ ] Success toast shown
- [ ] Switch to another file with unsaved changes — discard confirmation shown

- [ ] **Generation info (right panel):** shows provider, model, tokens, cost, duration, cached status

- [ ] **Regenerate:**
- [ ] Click "Regenerate" — confirmation dialog appears
- [ ] Confirm — "Regenerating..." overlay shown
- [ ] After completion — files reloaded, new generation info displayed

- [ ] **Toolbar:**
- [ ] "Back to Wizard" link navigates to /wizard/{id}
- [ ] File path breadcrumb shows current file path

---

## Test 7: GitHub Export

> Requires GITHUB_CLIENT_ID/SECRET configured and user logged in via GitHub OAuth

- [ ] In Preview toolbar, click "Export" dropdown
- [ ] Two options: "Push to GitHub" and "Download ZIP"

### Push to GitHub

- [ ] Click "Push to GitHub"
- [ ] Repo name input shown (default: project slug)
- [ ] Optionally change repo name
- [ ] Click "Push" — export starts
- [ ] Progress overlay: "Pushing to GitHub..."
- [ ] After completion: "What's Next" card shown with:
  - [ ] GitHub repo URL (clickable, opens in new tab)
  - [ ] Terminal-style clone/setup instructions
  - [ ] "Open on GitHub" button
- [ ] Visit the GitHub repo — all generated files present
- [ ] Repo is private

### Push to GitHub — Error cases

- [ ] Try exporting with a repo name that already exists → error "Repository name already exists"
- [ ] Try exporting without GitHub OAuth (dev-mode login) → "Connect GitHub first" message

### Download ZIP

- [ ] Click "Download ZIP"
- [ ] ZIP file downloads with project slug as filename
- [ ] Extract ZIP — all generated files present with correct directory structure
- [ ] No .git directory in ZIP

---

## Test 8: BYOS Deploy — Hetzner Auto-Provision

> Requires a Hetzner Cloud API key (https://console.hetzner.cloud → API Tokens)

**URL:** http://localhost:8000/projects/{id}/deploy

> Project must be exported to GitHub first

### Server Setup

- [ ] Deploy page shows "Connect a Server" with two cards
- [ ] Click "Create with Hetzner" card

- [ ] Enter Hetzner API key (get from https://console.hetzner.cloud/projects/{project}/security/tokens)
- [ ] Enter server name: "draplo-test"
- [ ] Select server type: CX22 (default)
- [ ] Click "Create Server"
- [ ] Status progresses: pending → provisioning → installing
- [ ] After 1-2 minutes: server IP shown, status "installing"
- [ ] Message: "Coolify is installing. Visit http://{ip}:8000 to complete setup"

### Coolify Setup (manual step after server provisioning)

- [ ] Wait 3-5 minutes for Coolify installation to complete
- [ ] Visit `http://{server-ip}:8000` in browser
- [ ] Complete Coolify initial setup (create admin account)
- [ ] Go to Coolify Settings → API → generate API key
- [ ] Copy the API key
- [ ] Back in Draplo: enter Coolify URL (`http://{ip}:8000`) and API key
- [ ] Click "Verify & Connect"
- [ ] Server status becomes "active" with green dot

### Deploy

- [ ] "Deploy" button now visible
- [ ] Click "Deploy" — confirmation shown
- [ ] Confirm — deploy starts
- [ ] Progress stepper: Creating app → Creating database → Setting environment → Building → Deploying → Live
- [ ] After 2-5 minutes: "Live" badge shown with URL
- [ ] Click the live URL — your deployed Laravel app is accessible
- [ ] SSL may take a few minutes to provision via Let's Encrypt

### Teardown

- [ ] Click "Teardown" (if available) — removes app from Coolify
- [ ] Project status reverts to "exported"

### Cleanup

- [ ] Delete the test server from Hetzner console (Draplo does not delete VPS for safety)
- [ ] Or keep it for future deploys

---

## Test 9: BYOS Deploy — Any Server (Manual Coolify Connect)

> Works with ANY Linux server: DigitalOcean, Hostinger, Linode, Vultr, AWS, bare metal, home server

### Prerequisites

1. A Linux server with SSH access (any provider)
2. Install Coolify on that server:
   ```bash
   ssh root@your-server-ip
   curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
   ```
3. Wait 3-5 minutes for installation
4. Visit `http://your-server-ip:8000` and complete Coolify setup
5. Go to Settings → API → generate an API key

### Connect in Draplo

- [ ] Visit /projects/{id}/deploy
- [ ] Click "Connect Existing Coolify" card
- [ ] Enter server name: "my-server"
- [ ] Enter Coolify URL: `http://your-server-ip:8000`
- [ ] Enter Coolify API key
- [ ] Click "Connect"
- [ ] Health check runs — if Coolify is reachable: status becomes "active"
- [ ] If unreachable: error "Could not connect to Coolify. Check URL and API key."

### Deploy

- [ ] Same flow as Hetzner deploy (Test 8, Deploy section)
- [ ] Click "Deploy" → progress → live URL
- [ ] Verify the app is accessible at the URL

### Different Server Providers Tested

| Provider | Server Type | Notes |
|----------|-------------|-------|
| DigitalOcean | Droplet (2GB+) | Install Coolify via SSH, connect manually |
| Hostinger | VPS | Same — install Coolify, connect URL + key |
| Linode | Nanode (2GB+) | Same flow |
| Vultr | Cloud Compute | Same flow |
| AWS | EC2 (t3.small+) | Open port 8000 in security group first |
| Home Server | Any Linux | Must have public IP or use tunneling (ngrok/cloudflared) |
| Raspberry Pi | Pi 4+ (4GB) | Works but slow builds — set higher Coolify timeouts |

### Common Issues

| Issue | Solution |
|-------|----------|
| "Could not connect to Coolify" | Check server IP, ensure port 8000 is open, verify API key |
| Coolify not reachable after install | Wait 5 minutes, check `docker ps` on server |
| Deploy fails at "Building" | Check Coolify dashboard for build logs, ensure enough RAM (2GB+) |
| SSL not working | Wait 5 minutes, Coolify uses Let's Encrypt — requires a domain pointing to the server |
| "Server unreachable" during deploy | Server may have rebooted — check health, reconnect if needed |

---

## Test 10: Dashboard + Settings + Admin

### Dashboard (`/dashboard`)

- [ ] 4 stat cards: Total Projects, Deployed, Generated, Plan
- [ ] Stats reflect actual data (create some projects first)
- [ ] Recent projects list (last 5)
- [ ] Quick action links: Preview / Deploy based on status
- [ ] "View All Projects" link → /projects
- [ ] AI Architect Terminal log shows generation events

### Settings (`/settings`)

- [ ] Profile section: name, email, GitHub username displayed
- [ ] Plan section: current plan badge shown
- [ ] GitHub connection: shows connected status + username
- [ ] Server connections: lists connected servers with status dots
- [ ] Health check button works (shows healthy/unhealthy toast)
- [ ] "Delete Account" button is disabled (coming soon)

### Admin (`/admin`)

> Only accessible when logged in as admin (dev user is admin)

- [ ] Platform stats: users, projects, generations, total cost, today's generations
- [ ] Active provider + model displayed
- [ ] **AI Settings form:**
- [ ] Provider dropdown: switch between Anthropic and Gemini
- [ ] Model name: editable text field
- [ ] Max tokens: editable number
- [ ] Rate limit: editable number
- [ ] Click "Save Settings" — toast confirms save
- [ ] Verify: change provider to Gemini, save, reload page — Gemini is still selected
- [ ] Non-admin user: visiting /admin redirects to /dashboard

---

## Test 11: Feature Flags

Test that feature flags correctly hide/show UI elements.

### API Test

- [ ] `GET /api/config/flags` returns all 6 flags as boolean values

### UI Tests

| Flag | Set to `false` | Expected Result |
|------|---------------|-----------------|
| `THREEJS_HERO_ENABLED` | `false` | Landing page shows gradient instead of 3D animation |
| `GITHUB_ENABLED` | `false` | GitHub export button hidden, only ZIP download shown |
| `COOLIFY_ENABLED` | `false` | Deploy features hidden from UI |
| `BYOS_HETZNER_ENABLED` | `false` | Hetzner card hidden in server setup, only manual connect |

After changing a flag in `.env`, run `php artisan config:clear` and reload.

---

## Test 12: Project List (`/projects`)

- [ ] Shows all user's projects
- [ ] Each project: name, template, status chip, last updated
- [ ] **Status chips** with correct colors:
  - Draft → amber
  - Wizard Done → primary
  - Generated → secondary (cyan)
  - Exported → green
  - Deployed → green
  - Failed → red
- [ ] "Resume" link for draft projects → navigates to wizard
- [ ] "Preview" link for generated projects → navigates to preview
- [ ] "Deploy" link for exported projects → navigates to deploy
- [ ] Delete button with confirmation dialog
- [ ] After delete: project removed from list, toast shown
- [ ] Empty state: "No projects yet" with link to templates

---

## Test 13: Automated Test Suite

```bash
# Run all tests
php artisan test

# Expected: 106+ passed, 0 failed
# One test may be skipped (ZIP on systems without ext-zip)

# Run specific test groups:
php artisan test tests/Feature/WizardTest.php
php artisan test tests/Feature/GenerationTest.php
php artisan test tests/Feature/ExportTest.php
php artisan test tests/Feature/ServerTest.php
php artisan test tests/Feature/DeployTest.php
php artisan test tests/Feature/AdminTest.php
php artisan test tests/Feature/GitHubAuthTest.php
php artisan test tests/Feature/FlagsTest.php

# Verify frontend build
npm run build
```

---

## Test 14: Production Mode Full Flow

> This tests the entire app as a real user would experience it in production — GitHub OAuth only, no dev-mode shortcuts.

### Production Environment Setup

```bash
# Set production-like environment
# In .env:
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8000

# Required keys:
GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-client-secret
GITHUB_REDIRECT_URL=http://localhost:8000/auth/github/callback
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-your-key

# Build for production
npm run build
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate:fresh --seed
```

### Full Production User Journey

**Step 1: Visit Landing Page**
- [ ] Visit http://localhost:8000
- [ ] Landing page loads with Three.js hero, all sections visible
- [ ] No "dev" or "debug" artifacts visible

**Step 2: Sign In via GitHub**
- [ ] Click "Sign In" → GitHub OAuth page
- [ ] Authorize → redirected back to Draplo
- [ ] Lands on /dashboard (not /templates — dashboard is default for authenticated)
- [ ] Dev login (`/dev/login`) returns 403 Forbidden

**Step 3: Browse Templates**
- [ ] Navigate to /templates via sidebar
- [ ] 25 templates + Start from Scratch visible
- [ ] Click "Booking Platform"

**Step 4: Complete Wizard**
- [ ] Step 1: Enter app name "MyBookingApp", customize description
- [ ] Step 2: Verify roles pre-populated, adjust if needed
- [ ] Step 3: Review 8 models, add a custom field
- [ ] Step 4: Verify multi-tenancy ON
- [ ] Step 5: Toggle integrations
- [ ] Step 6: Review summary, click "Generate Scaffold"

**Step 5: AI Generation**
- [ ] "Generating..." spinner shown
- [ ] `php artisan horizon` must be running in background
- [ ] After 15-30s: redirected to Preview page
- [ ] If using Gemini: switch in /admin first, then generate

**Step 6: Preview Generated Files**
- [ ] File tree shows 7+ generated files
- [ ] Click through files — syntax highlighted
- [ ] Edit a file → save → changes persist
- [ ] Generation info panel shows correct provider, tokens, cost

**Step 7: Export to GitHub**
- [ ] Click Export dropdown → "Push to GitHub"
- [ ] Enter repo name (or use default)
- [ ] Click "Push" → progress overlay
- [ ] "What's Next" card shows with repo URL
- [ ] Visit GitHub → private repo exists with all files
- [ ] Clone the repo → files match what was previewed

**Step 8: Download ZIP (alternative)**
- [ ] Click Export dropdown → "Download ZIP"
- [ ] ZIP downloads with project slug filename
- [ ] Extract → same files as in preview

**Step 9: Deploy (if server available)**
- [ ] Navigate to /projects/{id}/deploy
- [ ] **Option A (Hetzner):** Enter API key → create server → wait → enter Coolify key → deploy
- [ ] **Option B (Existing server):** Enter Coolify URL + key → connect → deploy
- [ ] After deploy: live URL shown, app accessible

**Step 10: Dashboard Verification**
- [ ] Visit /dashboard
- [ ] Stats reflect: 1 project, 1 generated, correct plan
- [ ] Recent projects shows your project
- [ ] Architect terminal shows generation log entry

**Step 11: Admin (if admin user)**
- [ ] Visit /admin
- [ ] Stats show 1 user, 1 project, 1 generation, cost > 0
- [ ] Change AI provider → save → verify persists on reload

**Step 12: Settings**
- [ ] Visit /settings
- [ ] GitHub: shows connected with your username
- [ ] Plan: shows "Free"
- [ ] Profile: shows your GitHub name/email

### Production Checklist

- [ ] `/dev/login` is blocked (403)
- [ ] All API endpoints require `Authorization: Bearer` token (except /api/templates and /api/config/flags)
- [ ] No debug information in error responses
- [ ] GitHub token is encrypted in database (`users.github_token` column is not plaintext)
- [ ] Server API keys are encrypted (`server_connections.encrypted_api_key` is not plaintext)
- [ ] CORS: SPA runs on same origin as API (no cross-origin issues in production)
- [ ] Static assets served from `/build/` (Vite production build)
- [ ] No console.log or debug output in browser console

### Revert to Development Mode

```bash
# After production testing, revert:
# In .env:
APP_ENV=local
APP_DEBUG=true

php artisan config:clear
```

---

## Test Summary Checklist

| Area | Tests | Status |
|------|-------|--------|
| Landing Page | 18 checks | [ ] |
| Authentication (dev + GitHub OAuth) | 20+ checks | [ ] |
| Template Library | 11 checks | [ ] |
| Wizard (6 steps) | 35+ checks | [ ] |
| AI Generation | 8 checks | [ ] |
| Preview | 20+ checks | [ ] |
| GitHub Export | 8 checks | [ ] |
| BYOS Deploy — Hetzner | 12 checks | [ ] |
| BYOS Deploy — Any Server | 8 checks | [ ] |
| Dashboard + Settings + Admin | 15+ checks | [ ] |
| Feature Flags | 5 checks | [ ] |
| Project List | 10 checks | [ ] |
| Automated Tests | 106+ tests | [ ] |
| **Production Mode Full Flow** | **20+ checks** | [ ] |
| **TOTAL** | **~200 checks** | |
