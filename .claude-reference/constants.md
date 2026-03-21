# Draplo — Constants & Enums

## User Plans
| Constant    | Value        | Description                                    |
|-------------|--------------|------------------------------------------------|
| FREE        | `free`       | Can generate + preview. Cannot export or deploy |
| PAID        | `paid`       | One-time $29-49 paid. Unlimited generate + export |
| SUBSCRIBER  | `subscriber` | Paid + $10/mo hosting. Can deploy to Coolify   |

## Project Statuses
| Constant    | Value        | Description                                    |
|-------------|--------------|------------------------------------------------|
| DRAFT       | `draft`      | Wizard started but not completed               |
| WIZARD_DONE | `wizard_done`| Wizard completed, not yet generated            |
| GENERATING  | `generating` | AI generation in progress (queued job running) |
| GENERATED   | `generated`  | AI output ready, available for preview         |
| EXPORTED    | `exported`   | Pushed to GitHub or downloaded as ZIP          |
| DEPLOYING   | `deploying`  | Coolify deploy in progress                     |
| DEPLOYED    | `deployed`   | Live on Coolify with URL                       |
| FAILED      | `failed`     | Generation or deploy failed                    |

## Wizard Steps
| Step | ID              | Title              | Required |
|------|-----------------|--------------------| ---------|
| 1    | `describe`      | Describe Your App  | Yes      |
| 2    | `users`         | Who Uses It?       | Yes      |
| 3    | `models`        | Core Models        | Yes      |
| 4    | `auth`          | Auth & Tenancy     | Yes      |
| 5    | `integrations`  | Integrations       | No       |
| 6    | `review`        | Review & Generate  | Yes      |

## App Types (Step 2)
| Value          | Label              |
|----------------|--------------------|
| `b2b_saas`     | B2B SaaS           |
| `b2c_app`      | B2C Application    |
| `marketplace`  | Marketplace        |
| `internal`     | Internal Tool      |
| `api_service`  | API Service        |

## Integration Options (Step 5)
| Value          | Label                    | Generates               |
|----------------|--------------------------|-------------------------|
| `stripe`       | Stripe Payments          | Cashier config, webhook |
| `sms`          | SMS (Infobip/Twilio)     | Notification channel    |
| `email`        | Transactional Email      | Mail config, templates  |
| `file_storage` | File Storage (S3/MinIO)  | Filesystem config       |
| `ai`           | AI Integration           | Anthropic/OpenAI setup  |
| `search`       | Full-Text Search         | Scout + Meilisearch     |
| `websockets`   | Real-time (WebSockets)   | Reverb config           |

## AI Models Used
| Purpose              | Model              | Cost (input/output per 1M) |
|----------------------|--------------------|-----------------------------|
| Project generation   | claude-sonnet-4-6  | $3 / $15                    |
| Model suggestions    | claude-sonnet-4-6  | $3 / $15                    |

## Rate Limits
| Action              | Limit                    |
|---------------------|--------------------------|
| AI generation       | 5 per hour per user      |
| GitHub export       | 10 per hour per user     |
| Coolify deploy      | 3 per hour per user      |
| API general         | 60 per minute per user   |

## Skeleton Versions
| Version | Laravel | Date       | Notes                     |
|---------|---------|------------|---------------------------|
| 1.0.0   | 12.x   | 2026-03-20 | Initial: Sanctum, tenancy, Docker, deploy scripts |

## Generated File Specs
| File                        | Source    | Approx Tokens | Description                    |
|-----------------------------|-----------|---------------|--------------------------------|
| CLAUDE.md                   | AI        | 2000-3000     | Agent context, rules, stack    |
| PROJECT.md                  | AI        | 1500-2500     | Features, roles, glossary      |
| todo.md                     | AI        | 2000-3500     | Phased backlog with checkboxes |
| architecture.md             | AI        | 3000-5000     | DB schema, API, file tree      |
| constants.md                | AI        | 1000-2000     | Enums, statuses, types         |
| patterns.md                 | AI        | 1500-2500     | Code patterns                  |
| decisions.md                | AI        | 1000-1500     | Key decisions                  |
| migrations/*.php            | AI        | 500-1500      | Per model migration            |
| routes/api.php              | AI        | 500-1000      | Route stubs                    |
| docker-compose.yml          | Static    | —             | From skeleton                  |
| .claude-deploy/*            | Static    | —             | From skeleton                  |
| .env.example                | Mixed     | —             | Static + dynamic env vars      |
