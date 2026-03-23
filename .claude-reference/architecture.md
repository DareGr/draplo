# Draplo — Architecture Reference

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Cloudflare CDN + DNS                     │
├──────────────┬──────────────┬───────────────────────────────┤
│  Landing     │  Wizard      │  Dashboard                     │
│  Blade SSR   │  React SPA   │  React SPA                     │
│  + Three.js  │              │                                │
├──────────────┴──────────────┴───────────────────────────────┤
│                    Laravel 12 API                            │
│  ┌──────────┐  ┌───────────┐  ┌──────────┐  ┌───────────┐  │
│  │ Sanctum  │  │ Anthropic │  │ GitHub   │  │ BYOS      │  │
│  │ Auth     │  │ AI Service│  │ Service  │  │ Service   │  │
│  └──────────┘  └───────────┘  └──────────┘  └───────────┘  │
│                                              ┌───────────┐  │
│  ┌──────────┐  ┌───────────┐  ┌───────────┐               │
│  │ Reverb   │  │ Horizon   │  │ Provider  │               │
│  │ WebSocket│  │ Queue     │  │ APIs      │               │
│  └──────────┘  └───────────┘  │ (H/DO/L/V)│               │
│                                └───────────┘               │
├─────────────────────────────────────────────────────────────┤
│  PostgreSQL 16  │  Redis (queue + cache + sessions)          │
└─────────────────────────────────────────────────────────────┘
                            │
                    BYOS: deploys to
                            │
              ┌─────────────▼─────────────┐
              │  USER'S OWN SERVER        │
              │  (Hetzner/DO/Linode/Vultr)│
              │  Coolify (auto-installed) │
              │  User app containers      │
              │  User PostgreSQL DBs      │
              │  User owns everything     │
              └───────────────────────────┘
```

## Database Schema

### users
| Column              | Type          | Notes                              |
|---------------------|---------------|------------------------------------|
| id                  | BIGSERIAL PK  |                                    |
| name                | VARCHAR(255)  |                                    |
| email               | VARCHAR(255)  | UNIQUE                             |
| password            | VARCHAR(255)  | Nullable (GitHub OAuth users)      |
| github_id           | VARCHAR(100)  | GitHub user ID                     |
| github_token        | TEXT          | Encrypted, for repo creation       |
| github_username     | VARCHAR(100)  |                                    |
| avatar_url          | VARCHAR(500)  |                                    |
| generation_count    | INTEGER       | Default: 0                         |
| created_at          | TIMESTAMP     |                                    |
| updated_at          | TIMESTAMP     |                                    |

### projects
| Column              | Type          | Notes                              |
|---------------------|---------------|------------------------------------|
| id                  | BIGSERIAL PK  |                                    |
| user_id             | BIGINT FK     | → users.id                         |
| name                | VARCHAR(255)  | "QRMeni", "DentalSaaS"            |
| slug                | VARCHAR(100)  | URL-safe project identifier        |
| description         | TEXT          | Short description from wizard      |
| wizard_data         | JSONB         | Complete wizard input (all steps)  |
| generation_output   | JSONB         | Cached AI output (all files)       |
| skeleton_version    | VARCHAR(20)   | "1.0.0" — which template version   |
| input_hash          | VARCHAR(64)   | SHA-256 of wizard_data for caching |
| github_repo_url     | VARCHAR(500)  | After export                       |
| github_repo_name    | VARCHAR(200)  |                                    |
| coolify_app_id      | VARCHAR(100)  | After deploy                       |
| coolify_db_id       | VARCHAR(100)  |                                    |
| deploy_url          | VARCHAR(500)  | Live URL after deploy              |
| custom_domain       | VARCHAR(255)  | User's custom domain               |
| status              | VARCHAR(50)   | See constants.md                   |
| exported_at         | TIMESTAMP     |                                    |
| deployed_at         | TIMESTAMP     |                                    |
| created_at          | TIMESTAMP     |                                    |
| updated_at          | TIMESTAMP     |                                    |

**Index:** `idx_projects_user` ON (user_id, status)
**Index:** `idx_projects_hash` ON (input_hash) — for generation caching

### generations
| Column              | Type          | Notes                              |
|---------------------|---------------|------------------------------------|
| id                  | BIGSERIAL PK  |                                    |
| project_id          | BIGINT FK     | → projects.id                      |
| input_hash          | VARCHAR(64)   |                                    |
| prompt_tokens       | INTEGER       | Input tokens used                  |
| completion_tokens   | INTEGER       | Output tokens used                 |
| cost_usd            | DECIMAL(8,4)  | Calculated cost                    |
| model               | VARCHAR(100)  | "claude-sonnet-4-6"               |
| duration_ms         | INTEGER       | API call duration                  |
| cached              | BOOLEAN       | Whether cache hit was used         |
| created_at          | TIMESTAMP     |                                    |

### server_connections
| Column              | Type          | Notes                              |
|---------------------|---------------|------------------------------------|
| id                  | BIGSERIAL PK  |                                    |
| user_id             | BIGINT FK     | → users.id                         |
| provider            | VARCHAR(50)   | 'hetzner', 'digitalocean', 'linode', 'vultr' |
| encrypted_api_key   | TEXT          | Laravel encrypt() — NEVER log      |
| server_id           | VARCHAR(255)  | Provider's server/droplet ID       |
| server_ip           | VARCHAR(45)   | IPv4/IPv6                          |
| coolify_url         | VARCHAR(500)  | https://coolify.{ip}.sslip.io      |
| coolify_api_key     | TEXT          | Encrypted — for Coolify API calls  |
| server_name         | VARCHAR(255)  | User-friendly name                 |
| server_spec         | VARCHAR(100)  | e.g., "CX22 (2 vCPU, 4GB RAM)"    |
| status              | VARCHAR(50)   | 'pending', 'provisioning', 'active', 'error' |
| last_health_check   | TIMESTAMP     |                                    |
| created_at          | TIMESTAMP     |                                    |
| updated_at          | TIMESTAMP     |                                    |

**Index:** `idx_server_user` ON (user_id, status)

## API Endpoints

### Auth
| Method | Endpoint                    | Description                    | Auth   |
|--------|-----------------------------|--------------------------------|--------|
| GET    | /auth/github                | Redirect to GitHub OAuth       | No     |
| GET    | /auth/github/callback       | GitHub OAuth callback          | No     |
| POST   | /api/auth/logout            | Logout                         | Yes    |
| GET    | /api/auth/me                | Current user + plan info       | Yes    |

### Wizard
| Method | Endpoint                          | Description                    | Auth   |
|--------|-----------------------------------|--------------------------------|--------|
| POST   | /api/wizard/projects              | Create new project (draft)     | Yes    |
| PUT    | /api/wizard/projects/{id}         | Update wizard data (per step)  | Yes    |
| GET    | /api/wizard/projects/{id}         | Get project with wizard data   | Yes    |
| POST   | /api/wizard/projects/{id}/suggest | AI suggest models from desc    | Yes    |

### Generation
| Method | Endpoint                              | Description                | Auth   |
|--------|---------------------------------------|----------------------------|--------|
| POST   | /api/projects/{id}/generate           | Trigger AI generation      | Yes    |
| GET    | /api/projects/{id}/generation         | Get generation output      | Yes    |
| POST   | /api/projects/{id}/regenerate         | Re-generate (new API call) | Yes    |
| GET    | /api/projects/{id}/preview            | Get all generated files    | Yes    |
| GET    | /api/projects/{id}/preview/{filepath} | Get single file content    | Yes    |

### Export
| Method | Endpoint                              | Description                | Auth   |
|--------|---------------------------------------|----------------------------|--------|
| POST   | /api/projects/{id}/export/github      | Push to GitHub repo        | Yes    |
| GET    | /api/projects/{id}/export/zip         | Download as ZIP            | Yes    |
| GET    | /api/projects/{id}/export/status      | GitHub push status         | Yes    |

### Deploy (BYOS — Bring Your Own Server)
| Method | Endpoint                              | Description                        | Auth        |
|--------|---------------------------------------|------------------------------------|-------------|
| GET    | /api/servers                          | List user's server connections     | Yes         |
| POST   | /api/servers                          | Add server (provider + API key)    | Yes         |
| DELETE | /api/servers/{id}                     | Remove server connection           | Yes         |
| GET    | /api/servers/{id}/health              | Server health check                | Yes         |
| POST   | /api/servers/{id}/provision           | Provision VPS + install Coolify    | Yes         |
| GET    | /api/servers/{id}/provision/status    | Provisioning progress              | Yes         |
| POST   | /api/projects/{id}/deploy             | Deploy to user's server            | Yes         |
| GET    | /api/projects/{id}/deploy/status      | Deploy progress                    | Yes         |
| POST   | /api/projects/{id}/deploy/redeploy    | Force redeploy                     | Yes         |
| PUT    | /api/projects/{id}/deploy/domain      | Set custom domain                  | Yes         |
| DELETE | /api/projects/{id}/deploy             | Tear down deployment               | Yes         |

### Dashboard
| Method | Endpoint                    | Description                    | Auth   |
|--------|-----------------------------|--------------------------------|--------|
| GET    | /api/projects               | List user's projects           | Yes    |
| DELETE | /api/projects/{id}          | Delete project                 | Yes    |
| GET    | /api/account                | Account details + usage stats  | Yes    |
### Admin
| Method | Endpoint                    | Description                    | Auth   |
|--------|-----------------------------|--------------------------------|--------|
| GET    | /api/admin/stats            | Platform stats                 | Admin  |
| GET    | /api/admin/users            | User list with usage           | Admin  |
| GET    | /api/admin/generations      | Generation log with costs      | Admin  |
| PUT    | /api/admin/prompt           | Update system prompt template  | Admin  |
| GET    | /api/admin/coolify/health   | Coolify server health          | Admin  |

## File Structure

```
draplo/
├── CLAUDE.md
├── PROJECT.md
├── todo.md
├── docker-compose.yml
├── .claude-reference/
│   ├── architecture.md          ← THIS FILE
│   ├── constants.md
│   ├── patterns.md
│   ├── decisions.md
│   ├── features/
│   │   ├── ai-generation.md     ← System prompt docs + testing strategy
│   │   └── output-templates.md  ← What gets generated, file specs
│   ├── plans/
│   │   └── business-plan.md
│   └── todo/
├── .claude-ui-reference/
│   ├── screens/
│   └── components/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── GitHubAuthController.php
│   │   │   ├── Wizard/
│   │   │   │   └── WizardController.php
│   │   │   ├── Generation/
│   │   │   │   └── GenerationController.php
│   │   │   ├── Export/
│   │   │   │   ├── GitHubExportController.php
│   │   │   │   └── ZipExportController.php
│   │   │   ├── Deploy/
│   │   │   │   └── CoolifyDeployController.php
│   │   │   ├── Dashboard/
│   │   │   │   └── ProjectController.php
│   │   │   └── Admin/
│   │   │       └── AdminController.php
│   │   ├── Middleware/
│   │   │   └── RateLimitGeneration.php
│   │   └── Requests/
│   │       ├── UpdateWizardRequest.php
│   │       └── GenerateRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Project.php
│   │   ├── Generation.php
│   ├── Services/
│   │   ├── AnthropicService.php      ← Claude API wrapper with caching
│   │   ├── GenerationService.php     ← Orchestrates full generation flow
│   │   ├── OutputParserService.php   ← Splits AI response into files
│   │   ├── GitHubService.php         ← Repo creation + file push
│   │   ├── CoolifyService.php        ← Deploy, DB provision, SSL
│   │   ├── SkeletonService.php       ← Merges static skeleton with AI output
│   ├── Jobs/
│   │   ├── GenerateProjectJob.php    ← Queued AI generation
│   │   ├── PushToGitHubJob.php       ← Queued repo creation + push
│   │   └── DeployToCoolifyJob.php    ← Queued Coolify deploy
│   └── Prompts/
│       ├── system-prompt.md          ← The master system prompt
│       └── model-suggestion.md       ← Prompt for suggesting models
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── views/
│   │   ├── landing.blade.php
│   │   ├── app.blade.php            ← React SPA mount point
│   │   └── emails/
│   └── js/
│       ├── app/                      ← React SPA
│       │   ├── App.jsx
│       │   ├── pages/
│       │   │   ├── Wizard/
│       │   │   │   ├── WizardLayout.jsx
│       │   │   │   ├── StepDescribe.jsx
│       │   │   │   ├── StepUsers.jsx
│       │   │   │   ├── StepModels.jsx
│       │   │   │   ├── StepAuth.jsx
│       │   │   │   ├── StepIntegrations.jsx
│       │   │   │   └── StepReview.jsx
│       │   │   ├── Preview/
│       │   │   │   ├── PreviewLayout.jsx
│       │   │   │   ├── FileTree.jsx
│       │   │   │   └── CodeViewer.jsx
│       │   │   ├── Dashboard/
│       │   │   │   ├── ProjectList.jsx
│       │   │   │   ├── ProjectDetail.jsx
│       │   │   │   └── AccountSettings.jsx
│       │   │   └── Deploy/
│       │   │       ├── DeployStatus.jsx
│       │   │       └── DomainSetup.jsx
│       │   └── components/
│       │       ├── WizardProgress.jsx
│       │       ├── ModelSuggester.jsx
│       │       └── DeployButton.jsx
├── routes/
│   ├── api.php
│   └── web.php
├── storage/
│   └── app/
│       └── skeletons/                ← Versioned Laravel skeleton templates
│           └── v1.0.0/
│               ├── docker-compose.yml
│               ├── .env.example
│               ├── .claude-deploy/
│               └── ... (static boilerplate files)
├── tests/
│   ├── Feature/
│   │   ├── WizardTest.php
│   │   ├── GenerationTest.php
│   │   ├── ExportTest.php
│   │   └── DeployTest.php
│   └── Unit/
│       ├── AnthropicServiceTest.php
│       ├── OutputParserServiceTest.php
│       └── SkeletonServiceTest.php
└── config/
    └── services.php                  ← Anthropic, GitHub, Coolify keys
```
