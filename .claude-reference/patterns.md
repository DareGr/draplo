# Draplo — Code Patterns

## Anthropic API Call with Prompt Caching

```php
// app/Services/AnthropicService.php
class AnthropicService
{
    public function generate(string $userMessage, ?string $cachedSystemPrompt = null): array
    {
        $systemPrompt = $cachedSystemPrompt ?? $this->getSystemPrompt();

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'prompt-caching-2024-07-31',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 16000,
            'system' => [
                [
                    'type' => 'text',
                    'text' => $systemPrompt,
                    'cache_control' => ['type' => 'ephemeral'], // Enable caching
                ],
            ],
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'],
            'input_tokens' => $data['usage']['input_tokens'],
            'output_tokens' => $data['usage']['output_tokens'],
            'cache_read' => $data['usage']['cache_read_input_tokens'] ?? 0,
        ];
    }
}
```

## Output Parsing Pattern

AI output uses XML tags to delimit files. This makes parsing reliable:

```php
// app/Services/OutputParserService.php
class OutputParserService
{
    public function parse(string $aiOutput): array
    {
        $files = [];
        $pattern = '/<file path="([^"]+)">(.*?)<\/file>/s';

        preg_match_all($pattern, $aiOutput, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $files[$match[1]] = trim($match[2]);
        }

        return $files;
    }
}
```

The system prompt instructs the AI to wrap each file in `<file path="CLAUDE.md">...</file>` tags.

## Generation Flow (Queued)

```php
// app/Services/GenerationService.php
class GenerationService
{
    public function generate(Project $project): void
    {
        // 1. Check cache
        if ($cached = $this->getCachedOutput($project->input_hash)) {
            $project->update([
                'generation_output' => $cached,
                'status' => 'generated',
            ]);
            return;
        }

        // 2. Build user message from wizard data
        $userMessage = $this->buildUserMessage($project->wizard_data);

        // 3. Call Anthropic API
        $result = $this->anthropic->generate($userMessage);

        // 4. Parse output into files
        $files = $this->parser->parse($result['content']);

        // 5. Store generation
        Generation::create([
            'project_id' => $project->id,
            'input_hash' => $project->input_hash,
            'prompt_tokens' => $result['input_tokens'],
            'completion_tokens' => $result['output_tokens'],
            'cost_usd' => $this->calculateCost($result),
            'model' => 'claude-sonnet-4-6',
            'cached' => $result['cache_read'] > 0,
        ]);

        // 6. Update project
        $project->update([
            'generation_output' => $files,
            'status' => 'generated',
        ]);
    }

    private function calculateCost(array $result): float
    {
        $inputCost = ($result['input_tokens'] / 1_000_000) * 3.0;   // $3/MTok
        $outputCost = ($result['output_tokens'] / 1_000_000) * 15.0; // $15/MTok
        // Cache reads cost 10% of input price
        $cacheCredit = ($result['cache_read'] / 1_000_000) * 3.0 * 0.9;
        return round($inputCost + $outputCost - $cacheCredit, 4);
    }
}
```

## GitHub Repo Creation Pattern

```php
// app/Services/GitHubService.php
class GitHubService
{
    public function createAndPushProject(User $user, Project $project): string
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "Bearer {$user->github_token}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        // 1. Create repo
        $repoResponse = $client->post('user/repos', [
            'json' => [
                'name' => $project->slug,
                'description' => $project->description,
                'private' => true,
                'auto_init' => false,
            ],
        ]);
        $repo = json_decode($repoResponse->getBody(), true);

        // 2. Merge skeleton + generated files
        $files = $this->skeleton->merge(
            $project->skeleton_version,
            $project->generation_output
        );

        // 3. Push all files via Git Data API (create tree + commit)
        $this->pushFiles($client, $repo['full_name'], $files);

        return $repo['html_url'];
    }
}
```

## BYOS (Bring Your Own Server) Deploy Pattern

```php
// app/Services/ServerProviderService.php — Abstract interface
interface ServerProviderInterface
{
    public function provision(ServerConnection $connection, array $spec): array;
    public function getStatus(ServerConnection $connection): string;
    public function destroy(ServerConnection $connection): bool;
}

// app/Services/Providers/HetznerProvider.php
class HetznerProvider implements ServerProviderInterface
{
    public function provision(ServerConnection $connection, array $spec): array
    {
        $apiKey = decrypt($connection->encrypted_api_key);
        
        // 1. Create VPS via Hetzner API
        $server = Http::withToken($apiKey)
            ->post('https://api.hetzner.cloud/v1/servers', [
                'name' => "draplo-{$connection->user_id}",
                'server_type' => $spec['type'] ?? 'cx22',
                'image' => 'ubuntu-24.04',
                'location' => $spec['location'] ?? 'nbg1',
                'ssh_keys' => [$this->getDraploSshKeyId($apiKey)],
            ])->json();

        // 2. Wait for server ready, then install Coolify via SSH
        $this->installCoolify($server['server']['public_net']['ipv4']['ip']);
        
        return [
            'server_id' => $server['server']['id'],
            'server_ip' => $server['server']['public_net']['ipv4']['ip'],
        ];
    }
}

// app/Services/BYOSDeployService.php
class BYOSDeployService
{
    public function deploy(Project $project, ServerConnection $server): array
    {
        $coolifyApiKey = decrypt($server->coolify_api_key);
        $coolifyUrl = $server->coolify_url;
        
        // 1. Create app in Coolify (on USER'S server)
        $app = Http::withToken($coolifyApiKey)
            ->post("{$coolifyUrl}/api/v1/applications", [
                'name' => $project->slug,
                'git_repository' => $project->github_repo_url,
                'git_branch' => 'main',
                'build_pack' => 'dockerfile',
            ])->json();

        // 2. Create PostgreSQL database
        $db = Http::withToken($coolifyApiKey)
            ->post("{$coolifyUrl}/api/v1/databases", [
                'name' => "{$project->slug}_db",
                'type' => 'postgresql',
            ])->json();

        // 3. Set environment variables
        Http::withToken($coolifyApiKey)
            ->put("{$coolifyUrl}/api/v1/applications/{$app['uuid']}/env", [
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => $db['internal_host'],
                'DB_DATABASE' => $db['name'],
                // ... more env vars
            ]);

        // 4. Trigger deploy
        Http::withToken($coolifyApiKey)
            ->post("{$coolifyUrl}/api/v1/applications/{$app['uuid']}/deploy");

        return [
            'app_id' => $app['uuid'],
            'url' => $app['fqdn'],
        ];
    }
}
```

**Security rules for BYOS:**
- API keys are encrypted with Laravel's `encrypt()` — NEVER stored in plaintext
- Decryption happens only during active deploy/provision operations
- API keys are NEVER logged, NEVER included in debug output
- API keys are NEVER shown in UI after initial entry (masked: `hc1_****...****7f3a`)
- Server connections can be deleted — this removes the encrypted key from our database

## Wizard Data Structure (JSON)

```json
{
    "step_describe": {
        "name": "QRMeni",
        "description": "Digital menu platform for cafes and restaurants. Guest scans QR, sees menu, taps button to call waiter.",
        "problem": "Printed menus are expensive to update, guests wait too long for waiter attention"
    },
    "step_users": {
        "app_type": "b2b_saas",
        "roles": [
            {"name": "admin", "description": "Venue owner, manages menu and settings"},
            {"name": "staff", "description": "Waiter, receives call notifications"},
            {"name": "guest", "description": "Anonymous, scans QR, views menu"}
        ]
    },
    "step_models": {
        "models": [
            {"name": "Tenant", "description": "A venue (cafe/restaurant)", "fields": ["name", "address", "logo"]},
            {"name": "Category", "description": "Menu category", "fields": ["name", "icon", "sort_order"]},
            {"name": "MenuItem", "description": "Single menu item", "fields": ["name", "price", "image", "available"]},
            {"name": "Table", "description": "Physical table with QR", "fields": ["number", "qr_uuid"]},
            {"name": "WaiterCall", "description": "Call waiter request", "fields": ["table_id", "status"]}
        ]
    },
    "step_auth": {
        "multi_tenant": true,
        "auth_method": "sanctum",
        "guest_access": true
    },
    "step_integrations": {
        "selected": ["file_storage", "websockets"],
        "notes": "MinIO for images, Reverb for waiter notifications"
    }
}
```
