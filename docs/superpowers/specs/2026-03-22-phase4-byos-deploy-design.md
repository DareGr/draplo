# Phase 4 — BYOS Deploy Integration Design Spec

**Date:** 2026-03-22
**Status:** Approved
**Scope:** Server connection management (Hetzner auto-provision + manual Coolify connect), CoolifyService API wrapper, deploy flow, deploy UI
**Depends on:** Phase 3B (GitHub export — projects must have GitHub repo to deploy)

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Server providers | Hetzner auto-provision for MVP + manual Coolify connect for any server | Hetzner has cloud-init for automatic Coolify install. Manual connect covers Hostinger, DO, Linode, Vultr, bare metal, home servers — any Linux with Coolify. |
| No SSH credentials | Never ask for root passwords or SSH keys | Security concern. Auto-provision injects setup via cloud-init (provider API creates server). Manual connect only needs Coolify URL + API key. |
| Coolify install method | Cloud-init user data on Hetzner VPS creation | Server installs Coolify automatically on first boot. No SSH from our side. Poll until Coolify API is reachable. |
| Deploy execution | Queued job (DeployToCoolifyJob) | Deploy involves multiple Coolify API calls (create app, DB, env, trigger). Takes 2-5 minutes. Same async pattern as generation. |
| Deploy page location | Separate route `/projects/{id}/deploy` | Deploy has its own lifecycle (server setup, progress, build log, live URL). Doesn't fit in Preview page. |
| API key security | Laravel encrypt() at rest, NEVER logged, NEVER exposed after entry | Both Hetzner API keys and Coolify API keys are encrypted. Hetzner key is used only during provisioning. |
| Stripe gate | Deferred (skipped per user request) | Can wrap deploy endpoints with payment middleware later. |

---

## 1. Server Connection Model

### Migration: `server_connections`

| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL PK | |
| user_id | BIGINT FK | → users.id |
| provider | VARCHAR(50) | 'hetzner' or 'manual' |
| encrypted_api_key | TEXT | Encrypted Hetzner API key. NULL for manual connections. NEVER logged. |
| server_id | VARCHAR(255) | Hetzner server ID. NULL for manual. |
| server_ip | VARCHAR(45) | IPv4/IPv6 |
| coolify_url | VARCHAR(500) | e.g., `http://{ip}:8000` |
| coolify_api_key | TEXT | Encrypted Coolify API key |
| server_name | VARCHAR(255) | User-friendly label |
| server_spec | VARCHAR(100) | e.g., "CX22 (2 vCPU, 4GB RAM)" |
| status | VARCHAR(50) | 'pending', 'provisioning', 'installing', 'active', 'error' |
| last_health_check | TIMESTAMP | Nullable |
| timestamps | | |

Index: `(user_id, status)`

### ServerConnection Model

- Encrypted casts for `encrypted_api_key` and `coolify_api_key`
- Relationship: `belongsTo(User::class)`
- Status helpers: `isActive()`, `isProvisioning()`

---

## 2. Hetzner Provider

**`app/Services/Deploy/HetznerService.php`**

### createServer(string $apiKey, string $name, string $type = 'cx22'): array

Calls `POST https://api.hetzner.cloud/v1/servers`:
```json
{
    "name": "{name}",
    "server_type": "{type}",
    "image": "ubuntu-24.04",
    "location": "fsn1",
    "start_after_create": true,
    "user_data": "#!/bin/bash\ncurl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash"
}
```

Auth: `Authorization: Bearer {apiKey}`

Returns: `{ server_id, server_ip }`

### getServer(string $apiKey, string $serverId): array

Calls `GET https://api.hetzner.cloud/v1/servers/{id}`.

Returns: `{ status, ip }` — status is 'initializing', 'running', etc.

### deleteServer(string $apiKey, string $serverId): void

Calls `DELETE https://api.hetzner.cloud/v1/servers/{id}`.

**HTTP config:** timeout 30s, connect timeout 10s.

**Error handling:**
- 401: invalid API key
- 422: invalid server name or type
- 429: rate limit (retry with backoff)

---

## 3. CoolifyService

**`app/Services/Deploy/CoolifyService.php`** — wraps the Coolify REST API on the user's server.

All calls: `{coolify_url}/api/v1/...` with `Authorization: Bearer {coolify_api_key}`.

### Methods

**`healthcheck(string $url, string $apiKey): bool`**
- GET `/healthcheck`
- Returns true if 200 response within 10s, false otherwise

**`createApplication(ServerConnection $conn, string $repoUrl): array`**
- POST `/applications`
- Body: `{ github_repository: repoUrl, build_pack: 'nixpacks', ... }`
- Returns: `{ id, uuid, fqdn }`

**`createDatabase(ServerConnection $conn, string $name): array`**
- POST `/databases/postgresql`
- Body: `{ name, ... }`
- Returns: `{ id, connection_url, db_name, db_user, db_password }`

**`setEnvironmentVariables(ServerConnection $conn, string $appUuid, array $vars): void`**
- POST `/applications/{uuid}/envs`
- Body: array of `{ key, value, is_build_time: false }`

**`deploy(ServerConnection $conn, string $appUuid): array`**
- POST `/applications/{uuid}/deploy`
- Returns: `{ deployment_uuid }`

**`getDeployStatus(ServerConnection $conn, string $appUuid): array`**
- GET `/applications/{uuid}/status`
- Returns: `{ status, fqdn }`
- Status values: 'building', 'running', 'stopped', 'error'

**`configureDomain(ServerConnection $conn, string $appUuid, string $domain): void`**
- PATCH `/applications/{uuid}`
- Body: `{ fqdn: domain }`
- Coolify handles SSL via Let's Encrypt automatically

**HTTP config:** timeout 30s, connect timeout 10s.
**Error handling:** Wrap all calls. If Coolify unreachable: throw with "Server unreachable — check if server is running."

---

## 4. Jobs

### ProvisionServerJob

`$timeout = 600, $tries = 1` (server creation can take 3-5 minutes)

1. Set connection status to `provisioning`
2. Call `HetznerService::createServer()` — creates VPS with cloud-init
3. Store `server_id` and `server_ip`
4. Set status to `installing`
5. Poll `HetznerService::getServer()` every 15s until status is `running` (max 2 minutes)
6. Poll `CoolifyService::healthcheck(http://{ip}:8000)` every 15s until reachable (max 5 minutes)
7. Once Coolify is reachable: store `coolify_url`, set status to `active`
8. On failure: set status to `error`, log error

**Note on Coolify API key:** After Coolify installs via cloud-init, the initial API key is auto-generated. We need to either:
- Read it from the Coolify instance's initial setup (Coolify exposes it at first boot)
- Or require the user to manually copy it from their Coolify dashboard after provisioning

For MVP, use the hybrid approach: provision the server, then prompt the user to enter their Coolify API key from the Coolify dashboard (accessible at `http://{ip}:8000`). This avoids SSH entirely.

### DeployToCoolifyJob

`$timeout = 300, $tries = 1`

1. Set project status to `deploying`
2. Create Coolify application from `project.github_repo_url`
3. Create PostgreSQL database
4. Set environment variables (DB credentials, APP_KEY, APP_URL, etc.)
5. Trigger deploy
6. Poll deploy status every 10s until `running` or error (max 5 minutes)
7. On success: update project with `deploy_url`, `coolify_app_id`, `coolify_db_id`, `status: deployed`, `deployed_at`
8. On failure: set project status to `failed`, log error

---

## 5. API Endpoints

### Server Management (under `auth:sanctum`)

**`GET /api/servers`**
- List user's server connections
- Returns: array of servers with id, name, provider, server_ip, status

**`POST /api/servers`**
- Input for Hetzner: `{ provider: 'hetzner', api_key, name, server_type?: 'cx22' }`
  - Encrypts API key, creates ServerConnection with status `pending`, dispatches ProvisionServerJob
  - Returns 202
- Input for manual: `{ provider: 'manual', name, coolify_url, coolify_api_key }`
  - Validates Coolify health check
  - If healthy: creates ServerConnection with status `active`, returns 201
  - If unhealthy: returns 422 "Could not connect to Coolify"

**`GET /api/servers/{server}`**
- Server details including status
- Ownership validated

**`DELETE /api/servers/{server}`**
- Removes connection from DB
- Does NOT delete the actual VPS (user manages that via Hetzner dashboard)
- Returns 204

**`GET /api/servers/{server}/health`**
- Pings Coolify health endpoint
- Updates `last_health_check` timestamp
- Returns: `{ healthy: bool }`

### Deploy (under `auth:sanctum`)

**`POST /api/projects/{project}/deploy`**
- Input: `{ server_id }`
- Validates: ownership, project status is `exported`, server is `active`
- Dispatches DeployToCoolifyJob
- Returns 202

**`GET /api/projects/{project}/deploy/status`**
- Returns: `{ status, deploy_url?, coolify_app_id? }`
- Status: 'deploying', 'deployed', 'failed', or 'pending' (not yet deployed)

**`DELETE /api/projects/{project}/deploy`**
- Removes app from Coolify via API (does NOT delete server)
- Resets project deploy fields
- Returns 204

### Controllers

- `app/Http/Controllers/ServerController.php` — CRUD for server connections
- `app/Http/Controllers/DeployController.php` — deploy, status, teardown

---

## 6. Deploy UI

### Route: `/projects/:projectId/deploy`

**DeployPage.jsx** — main page within AppLayout. Two modes:

### Mode 1: No active server → Server Setup

**ServerSetup.jsx** — "Connect a Server" with two cards:

**Card 1: "Create with Hetzner"**
- Hetzner API key input (password type, never shown after entry)
- Server name input
- Server type selector (cx22 / cx32 dropdown)
- "Create Server" button → POST /api/servers → shows provisioning progress
- Progress steps: Creating VPS → Installing Coolify → Waiting for Coolify → Enter API Key → Connected
- After VPS is running + Coolify installed: prompt user to visit `http://{ip}:8000`, complete Coolify setup, copy API key back to Draplo
- API key input → validates health → status becomes `active`

**Card 2: "Connect Existing Coolify"**
- Coolify URL input (e.g., `https://coolify.myserver.com`)
- Coolify API key input (password type)
- "Connect" button → POST /api/servers → health check → connected or error

### Mode 2: Has active server → Deploy View

**DeployProgress.jsx** — deploy controls and progress:

- Server info card: name, IP, provider badge, status dot, last health check
- "Deploy" button (primary) — shows confirmation with server name
- Deploy progress steps (vertical stepper): Creating app → Creating database → Setting environment → Building → Deploying → Live
- Terminal-style build log card (`bg-surface-container-lowest font-mono text-xs`) — polls for status updates
- When deployed: live URL as clickable link, green "Live" badge

### Navigation

- Preview toolbar: "Deploy" button (next to Export) → navigates to `/projects/{id}/deploy`
- ProjectList: "Deploy" link for exported projects

---

## 7. Testing

### Feature/ServerTest.php
- Create Hetzner server dispatches ProvisionServerJob (Queue::fake)
- Create manual server validates Coolify health (Http::fake)
- Create manual server rejects unhealthy Coolify (Http::fake returns error)
- List servers returns only user's servers
- Delete server removes connection
- Health check pings Coolify endpoint
- Ownership validation

### Feature/DeployTest.php
- Deploy dispatches DeployToCoolifyJob (Queue::fake)
- Deploy requires exported project
- Deploy requires active server
- Deploy status returns correct state for deployed project
- Teardown removes Coolify app
- Ownership validation

### Feature/HetznerServiceTest.php
- Creates VPS via Hetzner API (Http::fake)
- Returns server_id and IP
- Handles API errors

### Feature/CoolifyServiceTest.php
- Health check returns true for healthy server (Http::fake)
- Health check returns false for unreachable server
- Creates application
- Creates database
- Triggers deploy

---

## 8. File Structure

### New files
```
app/Models/ServerConnection.php
app/Services/Deploy/HetznerService.php
app/Services/Deploy/CoolifyService.php
app/Http/Controllers/ServerController.php
app/Http/Controllers/DeployController.php
app/Jobs/ProvisionServerJob.php
app/Jobs/DeployToCoolifyJob.php
database/migrations/xxxx_create_server_connections_table.php
resources/js/pages/Deploy/DeployPage.jsx
resources/js/pages/Deploy/ServerSetup.jsx
resources/js/pages/Deploy/DeployProgress.jsx
tests/Feature/ServerTest.php
tests/Feature/DeployTest.php
tests/Feature/HetznerServiceTest.php
tests/Feature/CoolifyServiceTest.php
```

### Files to modify
```
routes/api.php                                    — server + deploy endpoints
resources/js/app.jsx                              — deploy route
resources/js/pages/ProjectList.jsx                — "Deploy" link for exported projects
resources/js/pages/Preview/PreviewToolbar.jsx     — "Deploy" button
```

---

## 9. Out of Scope

- DigitalOcean, Linode, Vultr providers (add same pattern later with their APIs + cloud-init)
- Stripe Pro+ subscription gate (skipped)
- Custom domain configuration UI (Coolify handles SSL automatically, domain config is defer)
- Auto-deploy webhook on git push (defer)
- Resource meters / CPU/RAM monitoring (defer to Phase 5 dashboard)
- Server deletion via Hetzner API (user manages via Hetzner dashboard)
- Multiple servers per user (MVP: one server, can deploy multiple projects to it)
