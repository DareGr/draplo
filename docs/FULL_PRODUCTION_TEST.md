# Draplo — Full Production Test Plan

**Domain:** draplo.com
**Environment:** Production (APP_ENV=production)
**Tester:** Must have a GitHub account

---

## Part 1: Server & Deployment Setup

### 1.1 Server Requirements

- [ ] VPS with 2+ vCPU, 4GB+ RAM (Hetzner CX22 recommended)
- [ ] Ubuntu 22.04 or 24.04
- [ ] Domain `draplo.com` pointing to server IP (A record)
- [ ] SSL certificate (via Coolify/Caddy/Certbot)

### 1.2 Application Deployment

- [ ] Clone repo: `git clone https://github.com/yourusername/draplo.git /var/www/draplo`
- [ ] `cd /var/www/draplo`
- [ ] `cp .env.example .env`
- [ ] Configure `.env`:

```env
APP_NAME=Draplo
APP_ENV=production
APP_DEBUG=false
APP_URL=https://draplo.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=draplo
DB_USERNAME=draplo
DB_PASSWORD=<strong-password>

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

AI_PROVIDER=anthropic
AI_MODEL=claude-sonnet-4-6
ANTHROPIC_API_KEY=sk-ant-<your-key>
GEMINI_API_KEY=<your-gemini-key>

GITHUB_CLIENT_ID=<your-github-client-id>
GITHUB_CLIENT_SECRET=<your-github-client-secret>
GITHUB_REDIRECT_URL=https://draplo.com/auth/github/callback

STRIPE_ENABLED=false
COOLIFY_ENABLED=true
GITHUB_ENABLED=true
PREMIUM_TEMPLATES_ENABLED=true
THREEJS_HERO_ENABLED=true
BYOS_HETZNER_ENABLED=true
```

- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm install && npm run build`
- [ ] `php artisan key:generate`
- [ ] `php artisan migrate --seed`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Configure web server (nginx/Caddy) to serve from `/var/www/draplo/public`
- [ ] Start Horizon: `php artisan horizon` (via supervisor for persistence)
- [ ] Verify: `curl -I https://draplo.com` returns 200

### 1.3 GitHub OAuth App Setup

- [ ] Go to https://github.com/settings/developers
- [ ] Create new OAuth App:
  - Application name: `Draplo`
  - Homepage URL: `https://draplo.com`
  - Authorization callback URL: `https://draplo.com/auth/github/callback`
- [ ] Copy Client ID → `GITHUB_CLIENT_ID` in `.env`
- [ ] Generate Client Secret → `GITHUB_CLIENT_SECRET` in `.env`
- [ ] `php artisan config:cache` to reload

### 1.4 DNS Verification

- [ ] `draplo.com` resolves to server IP: `dig draplo.com +short`
- [ ] HTTPS works: `curl -I https://draplo.com` shows `HTTP/2 200`
- [ ] HTTP redirects to HTTPS: `curl -I http://draplo.com` shows 301 → https

---

## Part 2: Landing Page Tests

### 2.1 First Load

- [ ] Open `https://draplo.com` in Chrome
- [ ] Page loads in under 3 seconds
- [ ] No console errors (DevTools → Console)
- [ ] Dark theme renders correctly (background #121316, not white flash)

### 2.2 Three.js Hero

- [ ] 3D geometric blocks visible and animating
- [ ] Blocks assemble into a scaffold structure
- [ ] Move mouse — camera shifts slightly (parallax)
- [ ] No frame drops (smooth 60fps — check DevTools → Performance)
- [ ] On mobile (or resize to 375px): 3D hidden, gradient fallback shown

### 2.3 Hero Content

- [ ] Headline: "Your next SaaS, architected by AI." — clearly readable over 3D background
- [ ] Subheadline visible below
- [ ] "Browse Templates" button — click → navigates to `https://draplo.com/templates`
- [ ] "View on GitHub" button — click → opens GitHub repo in new tab

### 2.4 Tech Stack Bar

- [ ] 6 technology items visible: Laravel, PostgreSQL, React, Tailwind, Claude, Coolify
- [ ] Each has Material Symbol icon + monospace label
- [ ] Horizontally centered, no overflow

### 2.5 How It Works

- [ ] Section heading visible
- [ ] 4 steps in a row (or 2x2 on tablet, 1 column on mobile):
  1. Describe (edit_note icon)
  2. Generate (smart_toy icon)
  3. Preview (code icon)
  4. Deploy (rocket_launch icon)
- [ ] Each has title + description text

### 2.6 Featured Blueprints

- [ ] 6 template cards visible (3x2 grid on desktop)
- [ ] Cards: Booking Platform, CRM, Invoice & Billing, Restaurant, Project Management, E-commerce
- [ ] Each card: icon, category badge, name, description, model count, complexity
- [ ] "See All 25 Templates →" link at bottom — click → /templates

### 2.7 Agent-Ready Section

- [ ] "Built for AI Coding Agents" heading
- [ ] Terminal card on right showing `.claude-reference/` folder structure
- [ ] Description text on left

### 2.8 Pricing Section

- [ ] 3 tier cards:
  - Free ($0) — "AI generation + preview"
  - Pro ($29 one-time) — "GitHub export + ZIP download"
  - Pro+ ($12/mo) — "Premium templates + BYOS deploy"
- [ ] Pro card has highlighted border
- [ ] CTA buttons present (informational since Stripe is disabled)

### 2.9 Open Source Section

- [ ] "Fully open source. AGPL-3.0." heading
- [ ] "Self-host for free. Forever." subtext
- [ ] GitHub CTA button visible

### 2.10 Footer

- [ ] Draplo logo/text
- [ ] Copyright "© 2026 Draplo"
- [ ] Links: Privacy, Terms, Security, Changelog

### 2.11 SEO

- [ ] View page source (Ctrl+U):
  - [ ] `<title>` contains "Draplo"
  - [ ] `<meta name="description">` present
  - [ ] `<meta property="og:title">` present
  - [ ] `<meta property="og:image">` present
  - [ ] `<link rel="canonical" href="https://draplo.com">`
- [ ] Visit `https://draplo.com/sitemap.xml` — valid XML with URLs
- [ ] Visit `https://draplo.com/robots.txt` — contains `Sitemap:` directive

### 2.12 Mobile Responsive

- [ ] Open on iPhone/Android or resize to 375px
- [ ] No horizontal scroll
- [ ] Hero text scales down, CTAs stack vertically
- [ ] Template cards stack to single column
- [ ] Pricing cards stack to single column
- [ ] Footer links wrap properly

---

## Part 3: Authentication

### 3.1 Dev Login is Blocked

- [ ] Visit `https://draplo.com/dev/login`
- [ ] Returns 403 Forbidden (NOT a JSON token)
- [ ] This confirms dev-mode auth is disabled in production

### 3.2 GitHub OAuth Sign In

- [ ] Visit `https://draplo.com`
- [ ] Click "Sign In" in top navigation bar
- [ ] Browser redirects to `https://github.com/login/oauth/authorize?client_id=...`
- [ ] GitHub page shows: "Draplo wants to access your GitHub account"
- [ ] Requested permissions include: `repo` (access to repositories), `user:email`
- [ ] Click "Authorize [your-app-name]"
- [ ] Browser redirects to `https://draplo.com/auth/callback#token=eyJ...`
- [ ] After ~1 second, auto-redirects to `https://draplo.com/dashboard`
- [ ] You are now logged in

### 3.3 Verify User Data

- [ ] Open browser DevTools → Application → Local Storage → `https://draplo.com`
- [ ] Key `auth_token` exists with a long JWT-like string
- [ ] Open DevTools → Console, run:
  ```js
  fetch('/api/auth/me', {headers: {'Authorization': 'Bearer ' + localStorage.getItem('auth_token')}}).then(r => r.json()).then(console.log)
  ```
- [ ] Response shows your GitHub `name`, `email`, `github_username`, `avatar_url`
- [ ] `plan` is `"free"`
- [ ] `is_admin` is `false` (unless you seeded yourself as admin)

### 3.4 Navigation After Login

- [ ] Sidebar shows: Dashboard, Library, Wizard, Deployments, Settings
- [ ] Top bar shows user section (avatar or initial)
- [ ] Clicking "Dashboard" → `https://draplo.com/dashboard`
- [ ] Clicking "Library" → `https://draplo.com/templates`

### 3.5 Sign In Again (returning user)

- [ ] Clear localStorage: DevTools → Application → Local Storage → Clear
- [ ] Refresh page — shows "Initializing..." or redirects to login
- [ ] Visit `https://draplo.com/auth/github`
- [ ] GitHub does NOT show authorization screen again (already authorized)
- [ ] Instant redirect back to Draplo → logged in
- [ ] Same user account (check /api/auth/me — same github_username)

### 3.6 Unauthorized API Access

- [ ] Clear localStorage token
- [ ] In DevTools Console:
  ```js
  fetch('/api/projects').then(r => console.log(r.status))
  ```
- [ ] Returns `401`
- [ ] Same for `/api/auth/me`, `/api/servers`, `/api/admin/settings`
- [ ] Public endpoints still work without auth:
  ```js
  fetch('/api/templates').then(r => r.json()).then(d => console.log(d.length + ' templates'))
  fetch('/api/config/flags').then(r => r.json()).then(console.log)
  ```
- [ ] Returns `25 templates` and feature flags object

---

## Part 4: Template Library

### 4.1 Browse Templates

- [ ] Navigate to `https://draplo.com/templates`
- [ ] Page title: "Template Library"
- [ ] Eyebrow text in monospace font
- [ ] 25 template cards + 1 "Start from Scratch" card = 26 total

### 4.2 Category Filter

- [ ] Filter bar visible: All, Operations, Sales, Content, Platform, Education, Health, Hospitality, Analytics, Specialized
- [ ] "All" is active by default (highlighted)
- [ ] Click "Operations" — only operations templates shown (Booking, Project Mgmt, Inventory, Field Service, Fleet)
- [ ] Click "Sales" — sales templates (CRM, Invoice, E-commerce, Subscription)
- [ ] Click "All" — all 25 templates shown again

### 4.3 Template Cards

- [ ] Each card shows: Material Symbol icon, category badge, name (bold), description, tag chips, footer
- [ ] Footer: model count with cyan dot (e.g., "8 Models") + complexity with colored dot
- [ ] Hover: card background shifts lighter
- [ ] **Available templates (5):** Booking, CRM, Invoice, Restaurant, Project Mgmt — click works
- [ ] **Unavailable templates (20):** click shows "Coming soon" toast notification

### 4.4 Start from Scratch

- [ ] "Start from Scratch" card has dashed border, centered content
- [ ] Click → creates project → redirects to wizard with empty fields
- [ ] Wizard step 1 has empty name, description, problem fields

### 4.5 Select Booking Platform Template

- [ ] Click "Booking Platform" card
- [ ] Redirected to `https://draplo.com/wizard/{projectId}`
- [ ] Wizard opens pre-populated with template data

---

## Part 5: Wizard — Complete Flow

### 5.1 Step 1: Describe Your App

**URL:** `https://draplo.com/wizard/{id}`

- [ ] Header: "Template: Booking Platform → Step 1 of 6"
- [ ] Material Symbol `edit_note` icon (NOT emoji)
- [ ] **Name field:** empty — type `DentBook`
- [ ] **Description field:** pre-filled "Online booking and appointment management platform"
- [ ] **Problem field:** pre-filled "Manual scheduling via phone/Viber leads to double bookings..."
- [ ] Edit description to: "Dental appointment booking for clinics and dental offices"
- [ ] Bottom bar shows "Next: Who Uses It?" button
- [ ] Click "Next"
- [ ] Wait for save (button shows loading briefly)
- [ ] Advances to Step 2

### 5.2 Step 2: Who Uses It?

- [ ] Material Symbol `group` icon
- [ ] **App Type:** "B2B SaaS" pill is pre-selected (purple/highlighted)
- [ ] Click "Marketplace" — selection changes. Click "B2B SaaS" back.
- [ ] **Roles:**
  - admin — "Business owner, manages everything" — has lock icon, cannot delete
  - provider — "Service provider (e.g., hairdresser, dentist, trainer)" — can rename, can delete
  - client — "Books appointments, receives reminders" — has lock icon
- [ ] Rename "provider" to "dentist" — field updates
- [ ] Click "Add Role" — new empty role row appears
- [ ] Enter name: "receptionist", description: "Manages front desk and scheduling"
- [ ] Delete the "receptionist" role — row disappears
- [ ] Click "Next"

### 5.3 Step 3: Core Models

- [ ] Material Symbol `schema` icon (NOT emoji like the mockup)
- [ ] **8 models** visible: Tenant, Service, Provider, Appointment, Client, WorkingHours, BlockedSlot, Reminder
- [ ] **Tenant model:**
  - "LOCKED" badge with lock icon
  - Cannot delete (no delete button)
  - Name not editable
  - Fields: name, address, phone, logo, timezone, working_hours
- [ ] **Appointment model:**
  - Fields with type indicators:
    - `client_id` — FK badge (purple tinted background)
    - `provider_id` — FK badge
    - `service_id` — FK badge
    - `starts_at` — T indicator (timestamp)
    - `ends_at` — T indicator
    - `status` — plain (string)
    - `notes` — plain (text)
- [ ] **Service model:**
  - `duration_minutes` — # indicator (integer)
  - `price` — $ indicator (decimal)
  - `active` — ? indicator (boolean)
- [ ] **Add a field** to Service: click "+ Add field"
  - Inline form appears: name input + type dropdown
  - Enter "category" with type "string"
  - Press Enter or click checkmark — field chip appears
  - Hover the chip — X button appears to remove
- [ ] **Add a new model:** click "Add New Core Model"
  - Empty model card appears
  - Type name: "Insurance"
  - Add fields: "name" (string), "coverage" (text), "active" (boolean)
- [ ] **Delete a model:** click delete (trash icon) on "Insurance" — card disappears
- [ ] **Relationships widget** (desktop, right sidebar):
  - Shows: "Appointment belongs to Client", "Appointment belongs to Provider", "Appointment belongs to Service"
  - Auto-detected from `_id` suffix fields
- [ ] Click "Next"

### 5.4 Step 4: Auth & Tenancy

- [ ] Material Symbol `shield` icon
- [ ] **Multi-tenancy toggle:** ON (green/active)
  - Description: "Each customer gets their own isolated workspace..."
  - Toggle OFF — toggle switches. Toggle ON again.
- [ ] **Auth method:** "Laravel Sanctum" shown as selected
  - "Additional auth methods coming in future versions" note
- [ ] **Guest access toggle:** ON
  - Description field visible: "Public booking page where clients book without registration"
  - Toggle OFF — description field hides
  - Toggle ON — description field reappears
- [ ] Click "Next"

### 5.5 Step 5: Integrations

- [ ] Material Symbol `extension` icon
- [ ] **7 integration cards** in 2-column grid:
  - Stripe Payments (payments icon) — toggle ON (pre-selected)
  - SMS Notifications (sms icon) — toggle ON (pre-selected)
  - Transactional Email (email icon) — toggle ON (pre-selected)
  - File Storage (cloud_upload icon) — toggle OFF
  - AI Integration (smart_toy icon) — toggle OFF
  - Full-Text Search (search icon) — toggle OFF
  - Real-time WebSockets (sync_alt icon) — toggle OFF
- [ ] Toggle "File Storage" ON — switch activates
- [ ] Toggle "Stripe Payments" OFF — switch deactivates
- [ ] **Notes textarea:** "SMS for appointment reminders, Stripe for online payment/deposit, email for confirmations"
- [ ] Add to notes: " Added file storage for patient documents."
- [ ] Click "Next"

### 5.6 Step 6: Review & Generate

- [ ] Material Symbol `checklist` icon
- [ ] **5 expandable sections:**

**App Description:**
- [ ] Shows: Name "DentBook", description (edited), problem statement
- [ ] "Edit" link present — click → jumps to Step 1
- [ ] Return to Step 6 — data preserved

**Users & Roles:**
- [ ] Shows: App Type "B2B SaaS"
- [ ] Roles: admin, dentist (renamed from provider), client
- [ ] "Edit" link → jumps to Step 2

**Core Models:**
- [ ] Shows 8 model chips with field counts
- [ ] "Edit" link → jumps to Step 3

**Auth & Tenancy:**
- [ ] Multi-tenant: Yes, Auth: Sanctum, Guest: Yes

**Integrations:**
- [ ] Shows enabled integrations as purple chips
- [ ] Notes displayed

### 5.7 Generate Scaffold

- [ ] "Generate Scaffold" button is active (primary gradient, not disabled)
- [ ] Click "Generate Scaffold"
- [ ] Button changes to "Generating..." with loading spinner
- [ ] **Wait 15-30 seconds** (Horizon queue worker processes the job)
- [ ] Automatically redirected to `https://draplo.com/projects/{id}/preview`
- [ ] If error: toast notification shown, button becomes active again

### 5.8 Wizard State Persistence

- [ ] Navigate away from wizard (go to /templates)
- [ ] Go to /projects — see the project listed with status "draft" or "wizard_done"
- [ ] Click "Resume" — wizard opens at Step 1 with all data preserved
- [ ] Navigate through steps — all previously entered data is intact

---

## Part 6: Preview

**URL:** `https://draplo.com/projects/{id}/preview`

### 6.1 Page Layout

- [ ] Three-panel layout: file tree (left), code viewer (center), generation info (right)
- [ ] AppLayout sidebar visible with "Preview" or project context

### 6.2 File Tree (Left Panel)

- [ ] Shows generated files in nested directory structure:
  ```
  CLAUDE.md
  PROJECT.md
  todo.md
  .claude-reference/
    architecture.md
    constants.md
    patterns.md
    decisions.md
  database/
    migrations/
      (multiple .php files)
  routes/
    api.php
  ```
- [ ] Directories are collapsible — click folder to collapse/expand
- [ ] All expanded by default
- [ ] File icons: `.md` = description icon, `.php` = code icon
- [ ] File sizes shown (e.g., "2.1 KB")
- [ ] First file (CLAUDE.md) is selected/highlighted

### 6.3 Code Viewer (Center Panel)

- [ ] CLAUDE.md content displayed with markdown syntax highlighting
- [ ] Line numbers visible on left
- [ ] Click `architecture.md` in file tree — content changes to architecture
- [ ] Architecture shows database schema tables, API endpoints
- [ ] Click a `.php` migration file — PHP syntax highlighting (keywords colored)
- [ ] Click `routes/api.php` — route definitions highlighted

### 6.4 Tabs

- [ ] Each clicked file opens in a tab above the code viewer
- [ ] Maximum 5 tabs open at once
- [ ] Clicking a tab switches to that file
- [ ] Active tab: highlighted with primary color top border
- [ ] Close a tab with X button — file removed from tabs
- [ ] Opening 6th file: oldest inactive tab auto-closes

### 6.5 Toolbar

- [ ] "Back to Wizard" link — click → returns to `/wizard/{id}`
- [ ] File path breadcrumb shows current file path in monospace
- [ ] Edit toggle button present
- [ ] "Regenerate" button (secondary style)
- [ ] Export dropdown button (primary style)

### 6.6 Inline Editing

- [ ] Click "Edit" toggle in toolbar — mode switches to editable
- [ ] Edit text in the code viewer — type some characters
- [ ] Tab shows modified indicator (dot)
- [ ] "Save Changes" button appears at bottom
- [ ] Click "Save Changes" — content saved
- [ ] Success toast notification
- [ ] Switch to another file without saving — discard confirmation dialog
- [ ] Click "Read-only" toggle — back to read-only mode

### 6.7 Generation Info (Right Panel)

- [ ] Provider: "anthropic" (or "gemini") badge
- [ ] Model: "claude-sonnet-4-6" in monospace
- [ ] Input tokens: number with commas (e.g., "3,245")
- [ ] Output tokens: number with commas
- [ ] Cache read tokens: number (green if > 0)
- [ ] Cost: "$0.XXXX" in monospace
- [ ] Duration: "X.Xs" in monospace
- [ ] Cached: "No" badge (first generation) or "Yes" (cached)
- [ ] Timestamp: date/time of generation

### 6.8 Regenerate

- [ ] Click "Regenerate" button
- [ ] Confirmation dialog: "This will regenerate all files. Any unsaved edits will be lost."
- [ ] Click "Cancel" — nothing happens
- [ ] Click "Regenerate" again → confirm
- [ ] "Regenerating..." overlay with spinner
- [ ] After 15-30 seconds: files reload, generation info updates, overlay dismisses
- [ ] Verify: files may have changed (new generation), generation info shows new timestamp

### 6.9 Content Quality Check

- [ ] Open `CLAUDE.md` — contains: project name "DentBook", stack info, rules, conventions
- [ ] Open `PROJECT.md` — contains: features list, user roles (admin, dentist, client), glossary
- [ ] Open `todo.md` — contains: phased backlog with `- [ ]` checkboxes, at least 3 phases
- [ ] Open `architecture.md` — contains: database schema tables (appointments, patients, etc.), API endpoints
- [ ] Open `constants.md` — contains: status enums, role types
- [ ] Open `patterns.md` — contains: service layer pattern, tenant scoping (if multi-tenant)
- [ ] Open `decisions.md` — contains: 3+ architectural decisions with rationale
- [ ] Open a migration file — valid PHP with `Schema::create`, correct column types
- [ ] Open `routes/api.php` — valid PHP with `Route::` definitions

---

## Part 7: Export to GitHub

### 7.1 GitHub Export

- [ ] In Preview toolbar, click "Export" dropdown
- [ ] Two options visible: "Push to GitHub" and "Download ZIP"
- [ ] Click "Push to GitHub"
- [ ] Repo name input shown, default: project slug (e.g., "dentbook")
- [ ] Change repo name to "dentbook-scaffold" (optional)
- [ ] Click "Push"
- [ ] Progress: "Pushing to GitHub..." overlay
- [ ] After 5-10 seconds: "What's Next" card appears

### 7.2 What's Next Card

- [ ] Title: "Your scaffold is ready!" with check icon
- [ ] GitHub repo URL shown as clickable link
- [ ] Terminal-style instructions:
  ```
  git clone https://github.com/yourusername/dentbook-scaffold
  cd dentbook-scaffold
  docker-compose up -d
  cp .env.example .env
  php artisan migrate --seed
  ```
- [ ] "Open on GitHub" button — click → opens repo in new tab
- [ ] "Close" button — dismisses the card

### 7.3 Verify GitHub Repo

- [ ] Visit the GitHub repo URL
- [ ] Repo is **private**
- [ ] Files present: CLAUDE.md, PROJECT.md, todo.md, .claude-reference/ folder, database/migrations/, routes/api.php
- [ ] Commit message: "Initial scaffold generated by Draplo"
- [ ] Branch: `main`
- [ ] File contents match what was shown in Preview

### 7.4 Clone and Verify

```bash
git clone https://github.com/yourusername/dentbook-scaffold
cd dentbook-scaffold
ls -la
cat CLAUDE.md | head -20
cat .claude-reference/architecture.md | head -30
```

- [ ] All files present
- [ ] Content matches preview
- [ ] No `.git` history from Draplo itself (clean single commit)

### 7.5 Download ZIP

- [ ] Go back to Preview (if you closed the What's Next card)
- [ ] Click Export dropdown → "Download ZIP"
- [ ] ZIP file downloads (filename: `dentbook.zip` or similar)
- [ ] Extract the ZIP
- [ ] Same files as in GitHub repo
- [ ] No `.git` directory in ZIP

### 7.6 Export Error Cases

- [ ] Create a new project, generate it, try to export to GitHub with a repo name that already exists
- [ ] Expected: error "Repository name already exists on your GitHub account"
- [ ] Try the export again with a different name — succeeds

---

## Part 8: BYOS Deploy — Hetzner Auto-Provision

> Requires a Hetzner Cloud account with API access.
> Cost: ~€0.01 for a few minutes of CX22 usage. Delete server after testing.

### 8.1 Get Hetzner API Key

- [ ] Go to https://console.hetzner.cloud
- [ ] Select your project (or create one)
- [ ] Go to Security → API Tokens → Generate API Token
- [ ] Name: "Draplo Test", permissions: Read & Write
- [ ] Copy the token (shown only once)

### 8.2 Navigate to Deploy Page

- [ ] Visit `https://draplo.com/projects/{id}/deploy` (project must be exported to GitHub)
- [ ] "Connect a Server" page shown with two cards
- [ ] Card 1: "Create with Hetzner"
- [ ] Card 2: "Connect Existing Coolify"

### 8.3 Create Hetzner Server

- [ ] Click "Create with Hetzner" card
- [ ] Enter Hetzner API key (input type=password — key is hidden)
- [ ] Enter server name: "draplo-test-server"
- [ ] Select server type: CX22 (default)
- [ ] Click "Create Server"
- [ ] Status changes: pending → provisioning
- [ ] After ~60 seconds: server IP address shown
- [ ] Status changes to: installing
- [ ] Message: "Coolify is installing on your server. This takes 3-5 minutes."

### 8.4 Coolify Setup

- [ ] Wait 3-5 minutes for Coolify installation
- [ ] Open new browser tab: `http://{server-ip}:8000`
- [ ] Coolify welcome page loads
- [ ] Create Coolify admin account (email + password)
- [ ] Complete initial setup wizard
- [ ] Go to Settings → API → click "Generate New Token"
- [ ] Copy the API token

### 8.5 Connect Coolify to Draplo

- [ ] Return to Draplo deploy page
- [ ] Coolify URL field should be pre-filled: `http://{server-ip}:8000`
- [ ] Paste the Coolify API key
- [ ] Click "Verify & Connect"
- [ ] Health check runs — green checkmark
- [ ] Server status becomes "active" with green dot

### 8.6 Deploy Project

- [ ] "Deploy" button now visible
- [ ] Click "Deploy"
- [ ] Confirmation dialog with server name
- [ ] Confirm — deploy starts
- [ ] Progress stepper shows steps:
  1. Creating app → (check)
  2. Creating database → (check)
  3. Setting environment → (check)
  4. Building → (in progress, animated)
  5. Deploying → (pending)
  6. Live → (pending)
- [ ] Terminal log area shows status messages
- [ ] After 2-5 minutes: all steps complete
- [ ] "Live" badge appears in green
- [ ] Live URL shown (e.g., `http://{server-ip}:3000` or Coolify-assigned URL)

### 8.7 Verify Deployed App

- [ ] Click the live URL
- [ ] Laravel welcome page or your app loads
- [ ] Check Coolify dashboard: app is listed, status "running"
- [ ] Check database: PostgreSQL database created

### 8.8 Cleanup (IMPORTANT — avoid charges)

- [ ] Go to https://console.hetzner.cloud
- [ ] Find server "draplo-test-server"
- [ ] Click Delete → confirm
- [ ] Server deleted (no more charges)
- [ ] In Draplo: delete server connection via /settings or API

---

## Part 9: BYOS Deploy — Any Server (Manual Coolify)

> Works with ANY Linux server: DigitalOcean, Hostinger, Linode, Vultr, AWS, home server.

### 9.1 Prepare Your Server

**Prerequisites:**
- Linux server (Ubuntu 22.04+ recommended)
- At least 2GB RAM, 2 vCPU
- Root SSH access
- Public IP address (or tunneling for home servers)
- Port 8000 open (for Coolify dashboard)

**Install Coolify:**
```bash
ssh root@your-server-ip
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

- [ ] Installation completes (takes 3-5 minutes)
- [ ] Visit `http://your-server-ip:8000` — Coolify dashboard loads
- [ ] Create admin account and complete setup
- [ ] Go to Settings → API → generate API token
- [ ] Copy the token

### 9.2 Connect in Draplo

- [ ] Visit `https://draplo.com/projects/{id}/deploy`
- [ ] Click "Connect Existing Coolify"
- [ ] Enter server name: "my-digitalocean" (or whatever you want)
- [ ] Enter Coolify URL: `http://your-server-ip:8000`
- [ ] Enter Coolify API key (paste from step 9.1)
- [ ] Click "Connect"
- [ ] Health check passes — status "active" with green dot

### 9.3 Deploy Project

- [ ] Same flow as Hetzner deploy (Part 8, steps 8.6-8.7)
- [ ] Click Deploy → confirm → progress → live URL
- [ ] Visit the live URL — app is running

### 9.4 Provider-Specific Notes

**DigitalOcean:**
- [ ] Use a Droplet with 2GB+ RAM
- [ ] Choose Ubuntu 24.04 image
- [ ] Enable monitoring (optional)
- [ ] Firewall: allow ports 22, 80, 443, 8000

**Hostinger VPS:**
- [ ] Use Ubuntu template
- [ ] SSH in with provided credentials
- [ ] May need to open port 8000 in firewall: `ufw allow 8000`

**Linode:**
- [ ] Use Nanode 2GB+ plan
- [ ] Choose Ubuntu 24.04 image
- [ ] Configure firewall to allow port 8000

**Vultr:**
- [ ] Use Cloud Compute with 2GB+ RAM
- [ ] Choose Ubuntu 24.04
- [ ] Firewall: allow ports 22, 80, 443, 8000

**AWS EC2:**
- [ ] Use t3.small or larger
- [ ] Choose Ubuntu 24.04 AMI
- [ ] Security Group: allow inbound 22, 80, 443, 8000
- [ ] Assign Elastic IP for static address

**Home Server / Raspberry Pi:**
- [ ] Must have public IP or use tunneling (Cloudflare Tunnel, ngrok)
- [ ] Raspberry Pi 4 with 4GB+ RAM works but builds are slow
- [ ] Port forward 8000 on your router (or use tunnel)

### 9.5 Troubleshooting

| Problem | Check |
|---------|-------|
| "Could not connect to Coolify" | Is port 8000 open? `curl http://ip:8000/api/v1/healthcheck` |
| Coolify not installing | Check RAM (2GB+), check Docker: `docker ps` |
| Deploy fails at "Building" | Check Coolify dashboard build logs, ensure enough disk space |
| App not accessible after deploy | Check Coolify app status, check assigned port |
| SSL not working | Need a domain pointing to server IP + Let's Encrypt needs port 80 open |

---

## Part 10: Dashboard

**URL:** `https://draplo.com/dashboard`

### 10.1 Stat Cards

- [ ] **Total Projects:** shows number matching your actual projects (e.g., 1 or 2)
- [ ] **Deployed:** count of projects with "deployed" status
- [ ] **Generated:** count of projects that have been generated
- [ ] **Plan:** shows "Free" badge
- [ ] Each card: large number in monospace, label below, Material Symbol icon

### 10.2 Recent Projects

- [ ] List shows your recent projects (up to 5)
- [ ] Each: project name (bold), template name (monospace), status chip (colored dot + text), last updated
- [ ] Status colors: draft=amber, wizard_done=primary, generated=cyan, exported=green, deployed=green
- [ ] "Preview" link visible for generated projects — click → goes to preview
- [ ] "Deploy" link visible for exported projects — click → goes to deploy
- [ ] "View All Projects →" link → goes to /projects

### 10.3 AI Architect Terminal

- [ ] Terminal-style card at bottom (dark background, monospace font)
- [ ] Shows generation events: timestamp, project name, template, status
- [ ] If no generations: "No generation events yet" message

---

## Part 11: Settings

**URL:** `https://draplo.com/settings`

### 11.1 Profile

- [ ] Shows your GitHub avatar (or initial placeholder)
- [ ] Name: your GitHub display name
- [ ] Email: your GitHub email
- [ ] GitHub username: your @username
- [ ] All fields are read-only

### 11.2 Plan

- [ ] Shows "Free" plan badge
- [ ] "View Pricing" link → scrolls to landing page pricing section

### 11.3 GitHub Connection

- [ ] Green dot + "Connected"
- [ ] Shows your GitHub username
- [ ] "Reconnect GitHub" link → `/auth/github` (re-authorizes)

### 11.4 Server Connections

- [ ] Lists any connected servers
- [ ] Each: name, IP address (monospace), status dot, provider badge
- [ ] Health check button — click → shows healthy/unhealthy toast
- [ ] If no servers: empty state message

### 11.5 Danger Zone

- [ ] "Delete Account" button is visible but disabled (grayed out)
- [ ] Hover shows "Coming soon" tooltip

---

## Part 12: Admin Panel

**URL:** `https://draplo.com/admin`

> Only accessible if `is_admin = true` in database. The dev seeder sets this, but for a real GitHub user you need to manually update:
> ```sql
> UPDATE users SET is_admin = true WHERE github_username = 'your-username';
> ```

### 12.1 Access Control

- [ ] Non-admin user visiting /admin → redirected to /dashboard
- [ ] Admin user visiting /admin → admin panel loads

### 12.2 Platform Stats

- [ ] Users count: matches actual user count
- [ ] Projects count: matches actual count
- [ ] Generations count: total generations
- [ ] Total Cost: sum of all generation costs in USD (e.g., "$0.1523")
- [ ] Generations Today: count for today
- [ ] Active Provider: shows "anthropic" or "gemini"
- [ ] Active Model: shows model name

### 12.3 AI Settings

- [ ] **Provider dropdown:** shows current provider (Anthropic/Gemini)
- [ ] Change to "Gemini"
- [ ] **Model input:** change to "gemini-2.5-pro"
- [ ] **Max tokens:** shows current value (16000)
- [ ] **Rate limit:** shows current value (5)
- [ ] Click "Save Settings"
- [ ] Success toast: "Settings saved"
- [ ] Reload page — values persist (Gemini is still selected)
- [ ] Change back to Anthropic + claude-sonnet-4-6, save

### 12.4 Verify Settings Applied

- [ ] After changing to Gemini in admin: generate a new project
- [ ] Check generation info in Preview → provider should show "gemini"
- [ ] Change back to Anthropic, generate again → provider shows "anthropic"

---

## Part 13: Project List

**URL:** `https://draplo.com/projects`

### 13.1 Project Display

- [ ] All your projects listed
- [ ] Each shows: name, template (monospace), status chip, last updated (monospace)
- [ ] Sorted by most recently updated first

### 13.2 Status Chips

- [ ] Draft → amber dot + "Draft"
- [ ] Wizard Done → primary dot + "Wizard Done"
- [ ] Generated → cyan dot + "Generated"
- [ ] Exported → green dot + "Exported"
- [ ] Deployed → green dot + "Deployed"

### 13.3 Actions

- [ ] Draft project: "Resume" link → /wizard/{id}
- [ ] Generated project: "Preview" link → /projects/{id}/preview
- [ ] Exported project: "Deploy" link → /projects/{id}/deploy
- [ ] Delete button (trash icon) on each project
- [ ] Click delete → confirmation dialog → confirm → project removed, toast shown

### 13.4 Empty State

- [ ] Delete all projects
- [ ] Empty state: "No projects yet" message with "Browse Templates" button
- [ ] Click "Browse Templates" → /templates

---

## Part 14: Feature Flags

### 14.1 API Test

- [ ] Visit `https://draplo.com/api/config/flags`
- [ ] Returns JSON with all 6 flags as booleans:
  ```json
  {
    "stripe_enabled": false,
    "coolify_enabled": true,
    "github_enabled": true,
    "premium_templates_enabled": true,
    "threejs_hero_enabled": true,
    "byos_hetzner_enabled": true
  }
  ```

### 14.2 Three.js Flag

- [ ] Set `THREEJS_HERO_ENABLED=false` in .env
- [ ] `php artisan config:cache`
- [ ] Reload landing page — gradient background instead of 3D hero
- [ ] Set back to `true`, `php artisan config:cache` — 3D hero returns

### 14.3 Coolify Flag

- [ ] Set `COOLIFY_ENABLED=false` in .env
- [ ] `php artisan config:cache`
- [ ] Deploy features should be hidden from UI
- [ ] Set back to `true`

### 14.4 GitHub Flag

- [ ] Set `GITHUB_ENABLED=false` in .env
- [ ] `php artisan config:cache`
- [ ] GitHub export option should be hidden, only ZIP download available
- [ ] Set back to `true`

---

## Part 15: Security Verification

### 15.1 Authentication

- [ ] `/dev/login` returns 403 in production
- [ ] All `/api/` endpoints (except /templates and /config/flags) require Bearer token
- [ ] Invalid token returns 401
- [ ] Expired/revoked token returns 401

### 15.2 Authorization

- [ ] User A cannot access User B's project: `GET /api/wizard/projects/{B's-id}` → 403
- [ ] User A cannot delete User B's project: `DELETE /api/projects/{B's-id}` → 403
- [ ] User A cannot export User B's project: `POST /api/projects/{B's-id}/export/github` → 403
- [ ] Non-admin cannot access admin endpoints: `GET /api/admin/settings` → 403

### 15.3 Encrypted Data

SSH into server and verify:
```bash
php artisan tinker
# Check GitHub token is encrypted (not plaintext):
User::first()->getRawOriginal('github_token')
# Should show encrypted string, not a readable token

# Check server API keys are encrypted:
\App\Models\ServerConnection::first()?->getRawOriginal('encrypted_api_key')
# Should show encrypted string
```

### 15.4 No Debug Info

- [ ] Visit a non-existent URL: `https://draplo.com/api/nonexistent` → clean JSON error, no stack trace
- [ ] Submit invalid data to API → validation error, no debug info
- [ ] No `APP_DEBUG=true` indicators anywhere

### 15.5 HTTPS

- [ ] All pages served over HTTPS
- [ ] HTTP requests redirect to HTTPS
- [ ] No mixed content warnings in browser console
- [ ] OAuth callback uses HTTPS

---

## Part 16: Performance

### 16.1 Page Load Times

- [ ] Landing page: < 3 seconds on 4G connection
- [ ] Template Library: < 2 seconds
- [ ] Dashboard: < 2 seconds
- [ ] Preview page: < 2 seconds (after generation)

### 16.2 Three.js Performance

- [ ] Open DevTools → Performance → Record for 5 seconds on landing page
- [ ] Frame rate: consistent 60fps (or 30fps minimum on lower-end)
- [ ] No memory leaks (memory doesn't grow continuously)
- [ ] Mobile: fallback gradient renders (no 3D attempted)

### 16.3 API Response Times

```bash
# From server or nearby client:
time curl -s https://draplo.com/api/templates > /dev/null
# Should be < 200ms

time curl -s https://draplo.com/api/config/flags > /dev/null
# Should be < 100ms
```

---

## Test Results Summary

| Test Area | Checks | Pass | Fail | Notes |
|-----------|--------|------|------|-------|
| Server Setup | 15 | | | |
| Landing Page | 30+ | | | |
| Authentication | 20+ | | | |
| Template Library | 12 | | | |
| Wizard (6 steps) | 50+ | | | |
| Preview | 30+ | | | |
| GitHub Export | 15 | | | |
| ZIP Download | 5 | | | |
| BYOS — Hetzner | 15 | | | |
| BYOS — Any Server | 12 | | | |
| Dashboard | 8 | | | |
| Settings | 10 | | | |
| Admin Panel | 12 | | | |
| Project List | 10 | | | |
| Feature Flags | 8 | | | |
| Security | 12 | | | |
| Performance | 8 | | | |
| **TOTAL** | **~270** | | | |

---

**Tested by:** ___________________
**Date:** ___________________
**Environment:** Production (draplo.com)
**Browser:** ___________________
**Overall Result:** PASS / FAIL
