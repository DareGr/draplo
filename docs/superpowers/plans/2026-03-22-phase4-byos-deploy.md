# Phase 4 — BYOS Deploy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable users to deploy their generated Laravel scaffolds to their own servers via Coolify — either by auto-provisioning a Hetzner VPS or connecting an existing Coolify instance.

**Architecture:** ServerConnection model tracks server connections (Hetzner or manual). HetznerService creates VPS via Hetzner API with cloud-init for Coolify installation. CoolifyService wraps the Coolify REST API for app creation, database provisioning, and deployment. Two queued jobs handle async provisioning and deployment. Deploy UI provides server setup and deploy progress.

**Tech Stack:** Laravel 12, Hetzner Cloud API, Coolify REST API, React 18, Tailwind CSS 4

**Spec:** `docs/superpowers/specs/2026-03-22-phase4-byos-deploy-design.md`

---

## File Structure

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
database/factories/ServerConnectionFactory.php
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
resources/js/pages/ProjectList.jsx                — "Deploy" link
resources/js/pages/Preview/PreviewToolbar.jsx     — "Deploy" button
```

---

## Task 1: Migration + ServerConnection Model + Factory

**Files:**
- Create: `database/migrations/xxxx_create_server_connections_table.php`
- Create: `app/Models/ServerConnection.php`
- Create: `database/factories/ServerConnectionFactory.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration create_server_connections_table
```

Schema:
```php
Schema::create('server_connections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('provider', 50); // 'hetzner' or 'manual'
    $table->text('encrypted_api_key')->nullable(); // Hetzner API key, encrypted
    $table->string('server_id', 255)->nullable(); // Hetzner server ID
    $table->string('server_ip', 45)->nullable();
    $table->string('coolify_url', 500)->nullable();
    $table->text('coolify_api_key')->nullable(); // encrypted
    $table->string('server_name', 255);
    $table->string('server_spec', 100)->nullable();
    $table->string('status', 50)->default('pending');
    $table->timestamp('last_health_check')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'status']);
});
```

- [ ] **Step 2: Create ServerConnection model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'provider', 'encrypted_api_key', 'server_id',
        'server_ip', 'coolify_url', 'coolify_api_key', 'server_name',
        'server_spec', 'status', 'last_health_check',
    ];

    protected $hidden = ['encrypted_api_key', 'coolify_api_key'];

    protected function casts(): array
    {
        return [
            'encrypted_api_key' => 'encrypted',
            'coolify_api_key' => 'encrypted',
            'last_health_check' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isProvisioning(): bool
    {
        return in_array($this->status, ['pending', 'provisioning', 'installing']);
    }
}
```

- [ ] **Step 3: Create ServerConnectionFactory**

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'manual',
            'server_name' => fake()->words(2, true),
            'server_ip' => fake()->ipv4(),
            'coolify_url' => 'http://' . fake()->ipv4() . ':8000',
            'coolify_api_key' => fake()->sha256(),
            'status' => 'active',
        ];
    }

    public function hetzner(): static
    {
        return $this->state(fn() => [
            'provider' => 'hetzner',
            'encrypted_api_key' => fake()->sha256(),
            'server_id' => (string) fake()->numberBetween(10000, 99999),
            'server_spec' => 'CX22 (2 vCPU, 4GB RAM)',
        ]);
    }

    public function provisioning(): static
    {
        return $this->state(fn() => ['status' => 'provisioning']);
    }
}
```

- [ ] **Step 4: Add relationship to User model**

In `app/Models/User.php`, add:
```php
public function serverConnections(): HasMany
{
    return $this->hasMany(ServerConnection::class);
}
```

Add the import: `use Illuminate\Database\Eloquent\Relations\HasMany;` (if not already there).

- [ ] **Step 5: Run migration**

```bash
docker-compose up -d
php artisan migrate
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add server_connections migration, ServerConnection model, factory"
```

---

## Task 2: HetznerService + CoolifyService

**Files:**
- Create: `app/Services/Deploy/HetznerService.php`
- Create: `app/Services/Deploy/CoolifyService.php`

- [ ] **Step 1: Create HetznerService**

```php
<?php

namespace App\Services\Deploy;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HetznerService
{
    private const API_BASE = 'https://api.hetzner.cloud/v1';

    public function createServer(string $apiKey, string $name, string $type = 'cx22'): array
    {
        $response = $this->request($apiKey, 'POST', '/servers', [
            'name' => $name,
            'server_type' => $type,
            'image' => 'ubuntu-24.04',
            'location' => 'fsn1',
            'start_after_create' => true,
            'user_data' => "#!/bin/bash\ncurl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash",
        ]);

        $server = $response['server'];

        return [
            'server_id' => (string) $server['id'],
            'server_ip' => $server['public_net']['ipv4']['ip'] ?? null,
        ];
    }

    public function getServer(string $apiKey, string $serverId): array
    {
        $response = $this->request($apiKey, 'GET', "/servers/{$serverId}");
        $server = $response['server'];

        return [
            'status' => $server['status'],
            'ip' => $server['public_net']['ipv4']['ip'] ?? null,
        ];
    }

    public function deleteServer(string $apiKey, string $serverId): void
    {
        $this->request($apiKey, 'DELETE', "/servers/{$serverId}");
    }

    private function request(string $apiKey, string $method, string $path, array $data = [], int $retries = 0): array
    {
        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->{strtolower($method)}(self::API_BASE . $path, $data);

        if ($response->status() === 429 && $retries < 3) {
            $delay = pow(2, $retries);
            sleep($delay);
            Log::warning("Hetzner API rate limit, retrying in {$delay}s", ['path' => $path]);
            return $this->request($apiKey, $method, $path, $data, $retries + 1);
        }

        if ($response->status() === 401) {
            throw new \RuntimeException('Invalid Hetzner API key.');
        }

        if (!$response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("Hetzner API error ({$response->status()}): {$message}");
        }

        return $response->json() ?? [];
    }
}
```

- [ ] **Step 2: Create CoolifyService**

```php
<?php

namespace App\Services\Deploy;

use App\Models\ServerConnection;
use Illuminate\Support\Facades\Http;

class CoolifyService
{
    public function healthcheck(string $url, string $apiKey): bool
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->get("{$url}/api/v1/healthcheck");

            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function createApplication(ServerConnection $conn, string $repoUrl): array
    {
        return $this->request($conn, 'POST', '/applications', [
            'github_repository' => $repoUrl,
            'build_pack' => 'nixpacks',
        ]);
    }

    public function createDatabase(ServerConnection $conn, string $name): array
    {
        return $this->request($conn, 'POST', '/databases/postgresql', [
            'name' => $name,
        ]);
    }

    public function setEnvironmentVariables(ServerConnection $conn, string $appUuid, array $vars): void
    {
        foreach ($vars as $key => $value) {
            $this->request($conn, 'POST', "/applications/{$appUuid}/envs", [
                'key' => $key,
                'value' => $value,
                'is_build_time' => false,
            ]);
        }
    }

    public function deploy(ServerConnection $conn, string $appUuid): array
    {
        return $this->request($conn, 'POST', "/applications/{$appUuid}/deploy");
    }

    public function getDeployStatus(ServerConnection $conn, string $appUuid): array
    {
        return $this->request($conn, 'GET', "/applications/{$appUuid}/status");
    }

    public function deleteApplication(ServerConnection $conn, string $appUuid): void
    {
        $this->request($conn, 'DELETE', "/applications/{$appUuid}");
    }

    private function request(ServerConnection $conn, string $method, string $path, array $data = []): array
    {
        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->withHeaders([
                'Authorization' => "Bearer {$conn->coolify_api_key}",
                'Accept' => 'application/json',
            ])
            ->{strtolower($method)}("{$conn->coolify_url}/api/v1{$path}", $data);

        if (!$response->successful()) {
            $message = $response->json('message') ?? $response->body();
            throw new \RuntimeException("Coolify API error ({$response->status()}): {$message}");
        }

        return $response->json() ?? [];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add HetznerService and CoolifyService for server provisioning and deploy"
```

---

## Task 3: Jobs (ProvisionServerJob + DeployToCoolifyJob)

**Files:**
- Create: `app/Jobs/ProvisionServerJob.php`
- Create: `app/Jobs/DeployToCoolifyJob.php`

- [ ] **Step 1: Create ProvisionServerJob**

```php
<?php

namespace App\Jobs;

use App\Models\ServerConnection;
use App\Services\Deploy\CoolifyService;
use App\Services\Deploy\HetznerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public ServerConnection $connection
    ) {}

    public function handle(HetznerService $hetzner, CoolifyService $coolify): void
    {
        try {
            // Step 1: Create VPS
            $this->connection->update(['status' => 'provisioning']);

            $result = $hetzner->createServer(
                $this->connection->encrypted_api_key,
                $this->connection->server_name,
            );

            $this->connection->update([
                'server_id' => $result['server_id'],
                'server_ip' => $result['server_ip'],
                'coolify_url' => "http://{$result['server_ip']}:8000",
                'status' => 'installing',
            ]);

            // Step 2: Wait for server to be running
            $maxAttempts = 8; // 8 * 15s = 2 min
            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep(15);
                $server = $hetzner->getServer($this->connection->encrypted_api_key, $result['server_id']);
                if ($server['status'] === 'running') {
                    break;
                }
                if ($i === $maxAttempts - 1) {
                    throw new \RuntimeException('Server did not start within 2 minutes.');
                }
            }

            // Step 3: Wait for Coolify to be reachable
            // Note: Coolify takes 3-5 min to install. User will enter API key manually after.
            // We just wait for the server to be running, then set status so UI prompts for API key.

            $this->connection->update(['status' => 'installing']);

        } catch (\Throwable $e) {
            $this->connection->update(['status' => 'error']);
            Log::error('Server provisioning failed', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 2: Create DeployToCoolifyJob**

```php
<?php

namespace App\Jobs;

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Models\ServerConnection;
use App\Services\Deploy\CoolifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeployToCoolifyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public Project $project,
        public ServerConnection $connection,
    ) {}

    public function handle(CoolifyService $coolify): void
    {
        $this->project->update(['status' => ProjectStatusEnum::Deploying]);

        try {
            // Step 1: Create application
            $app = $coolify->createApplication($this->connection, $this->project->github_repo_url);

            // Step 2: Create database
            $dbName = Str::snake($this->project->slug) . '_db';
            $db = $coolify->createDatabase($this->connection, $dbName);

            // Step 3: Set env vars
            $coolify->setEnvironmentVariables($this->connection, $app['uuid'], [
                'APP_NAME' => $this->project->name,
                'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
                'APP_URL' => $app['fqdn'] ?? "http://{$this->connection->server_ip}",
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => $db['connection_url'] ?? 'localhost',
                'DB_DATABASE' => $db['db_name'] ?? $dbName,
                'DB_USERNAME' => $db['db_user'] ?? 'postgres',
                'DB_PASSWORD' => $db['db_password'] ?? '',
            ]);

            // Step 4: Trigger deploy
            $coolify->deploy($this->connection, $app['uuid']);

            // Step 5: Poll for completion
            $maxAttempts = 30; // 30 * 10s = 5 min
            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep(10);
                $status = $coolify->getDeployStatus($this->connection, $app['uuid']);
                if (($status['status'] ?? '') === 'running') {
                    break;
                }
                if (($status['status'] ?? '') === 'error') {
                    throw new \RuntimeException('Deploy failed on Coolify.');
                }
            }

            // Step 6: Update project
            $this->project->update([
                'coolify_app_id' => $app['uuid'],
                'coolify_db_id' => $db['id'] ?? null,
                'deploy_url' => $app['fqdn'] ?? "http://{$this->connection->server_ip}",
                'status' => ProjectStatusEnum::Deployed,
                'deployed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            $this->project->update(['status' => ProjectStatusEnum::Failed]);
            Log::error('Deploy to Coolify failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add ProvisionServerJob and DeployToCoolifyJob"
```

---

## Task 4: Controllers + Routes

**Files:**
- Create: `app/Http/Controllers/ServerController.php`
- Create: `app/Http/Controllers/DeployController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create ServerController**

```php
<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionServerJob;
use App\Models\ServerConnection;
use App\Services\Deploy\CoolifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ServerController extends Controller
{
    public function index(): JsonResponse
    {
        $servers = auth()->user()->serverConnections()
            ->select('id', 'provider', 'server_name', 'server_ip', 'server_spec', 'status', 'last_health_check', 'updated_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($servers);
    }

    public function store(Request $request, CoolifyService $coolify): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'in:hetzner,manual'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $provider = $request->input('provider');

        if ($provider === 'hetzner') {
            $request->validate([
                'api_key' => ['required', 'string'],
                'server_type' => ['sometimes', 'string', 'in:cx22,cx32,cx42'],
            ]);

            $connection = auth()->user()->serverConnections()->create([
                'provider' => 'hetzner',
                'encrypted_api_key' => $request->input('api_key'),
                'server_name' => $request->input('name'),
                'server_spec' => strtoupper($request->input('server_type', 'cx22')),
                'status' => 'pending',
            ]);

            ProvisionServerJob::dispatch($connection);

            return response()->json($connection, 202);
        }

        // Manual Coolify connect
        $request->validate([
            'coolify_url' => ['required', 'url', 'max:500'],
            'coolify_api_key' => ['required', 'string'],
        ]);

        $url = rtrim($request->input('coolify_url'), '/');
        $apiKey = $request->input('coolify_api_key');

        if (!$coolify->healthcheck($url, $apiKey)) {
            return response()->json(['message' => 'Could not connect to Coolify. Check URL and API key.'], 422);
        }

        $connection = auth()->user()->serverConnections()->create([
            'provider' => 'manual',
            'server_name' => $request->input('name'),
            'coolify_url' => $url,
            'coolify_api_key' => $apiKey,
            'status' => 'active',
            'last_health_check' => now(),
        ]);

        return response()->json($connection, 201);
    }

    public function show(ServerConnection $server): JsonResponse
    {
        if ($server->user_id !== auth()->id()) {
            abort(403);
        }

        return response()->json($server);
    }

    public function destroy(ServerConnection $server): Response
    {
        if ($server->user_id !== auth()->id()) {
            abort(403);
        }

        $server->delete();

        return response()->noContent();
    }

    public function health(ServerConnection $server, CoolifyService $coolify): JsonResponse
    {
        if ($server->user_id !== auth()->id()) {
            abort(403);
        }

        $healthy = false;
        if ($server->coolify_url && $server->coolify_api_key) {
            $healthy = $coolify->healthcheck($server->coolify_url, $server->coolify_api_key);
        }

        $server->update(['last_health_check' => now()]);

        return response()->json(['healthy' => $healthy]);
    }
}
```

- [ ] **Step 2: Create DeployController**

```php
<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatusEnum;
use App\Jobs\DeployToCoolifyJob;
use App\Models\Project;
use App\Models\ServerConnection;
use App\Services\Deploy\CoolifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeployController extends Controller
{
    public function deploy(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        if ($project->status !== ProjectStatusEnum::Exported) {
            return response()->json(['message' => 'Project must be exported to GitHub before deploying.'], 422);
        }

        $request->validate(['server_id' => ['required', 'integer']]);

        $server = ServerConnection::find($request->input('server_id'));

        if (!$server || $server->user_id !== auth()->id()) {
            return response()->json(['message' => 'Server not found.'], 404);
        }

        if (!$server->isActive()) {
            return response()->json(['message' => 'Server is not active.'], 422);
        }

        DeployToCoolifyJob::dispatch($project, $server);

        return response()->json([
            'status' => 'deploying',
            'message' => 'Deploy started.',
        ], 202);
    }

    public function status(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        if ($project->status === ProjectStatusEnum::Deploying) {
            return response()->json(['status' => 'deploying']);
        }

        if ($project->status === ProjectStatusEnum::Deployed) {
            return response()->json([
                'status' => 'deployed',
                'deploy_url' => $project->deploy_url,
                'coolify_app_id' => $project->coolify_app_id,
                'deployed_at' => $project->deployed_at?->toISOString(),
            ]);
        }

        return response()->json(['status' => 'pending']);
    }

    public function teardown(Request $request, Project $project, CoolifyService $coolify): Response
    {
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        if ($project->coolify_app_id) {
            $server = auth()->user()->serverConnections()->where('status', 'active')->first();
            if ($server) {
                try {
                    $coolify->deleteApplication($server, $project->coolify_app_id);
                } catch (\Exception) {
                    // Coolify might be unreachable, still clear local state
                }
            }
        }

        $project->update([
            'coolify_app_id' => null,
            'coolify_db_id' => null,
            'deploy_url' => null,
            'status' => ProjectStatusEnum::Exported,
            'deployed_at' => null,
        ]);

        return response()->noContent();
    }
}
```

- [ ] **Step 3: Add routes to api.php**

Read the file. Add imports and routes inside `auth:sanctum` group:

```php
use App\Http\Controllers\ServerController;
use App\Http\Controllers\DeployController;

// Servers
Route::get('/servers', [ServerController::class, 'index']);
Route::post('/servers', [ServerController::class, 'store']);
Route::get('/servers/{server}', [ServerController::class, 'show']);
Route::delete('/servers/{server}', [ServerController::class, 'destroy']);
Route::get('/servers/{server}/health', [ServerController::class, 'health']);

// Deploy
Route::post('/projects/{project}/deploy', [DeployController::class, 'deploy']);
Route::get('/projects/{project}/deploy/status', [DeployController::class, 'status']);
Route::delete('/projects/{project}/deploy', [DeployController::class, 'teardown']);
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add ServerController, DeployController, server + deploy routes"
```

---

## Task 5: Backend Tests

**Files:**
- Create: `tests/Feature/ServerTest.php`
- Create: `tests/Feature/DeployTest.php`
- Create: `tests/Feature/HetznerServiceTest.php`
- Create: `tests/Feature/CoolifyServiceTest.php`

- [ ] **Step 1: Create ServerTest**

```php
<?php

use App\Jobs\ProvisionServerJob;
use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates hetzner server and dispatches provision job', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson('/api/servers', [
            'provider' => 'hetzner',
            'name' => 'My Server',
            'api_key' => 'hetzner_test_key',
        ])
        ->assertStatus(202);

    Queue::assertPushed(ProvisionServerJob::class);
    $this->assertDatabaseHas('server_connections', [
        'user_id' => $this->user->id,
        'provider' => 'hetzner',
        'status' => 'pending',
    ]);
});

it('creates manual server with coolify health check', function () {
    Http::fake([
        '*/api/v1/healthcheck' => Http::response(['status' => 'ok']),
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/servers', [
            'provider' => 'manual',
            'name' => 'My Coolify',
            'coolify_url' => 'http://192.168.1.1:8000',
            'coolify_api_key' => 'coolify_key',
        ])
        ->assertStatus(201)
        ->assertJsonPath('status', 'active');
});

it('rejects manual server with unhealthy coolify', function () {
    Http::fake([
        '*/api/v1/healthcheck' => Http::response([], 500),
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/servers', [
            'provider' => 'manual',
            'name' => 'Bad Server',
            'coolify_url' => 'http://192.168.1.1:8000',
            'coolify_api_key' => 'bad_key',
        ])
        ->assertStatus(422);
});

it('lists only users servers', function () {
    ServerConnection::factory()->count(2)->create(['user_id' => $this->user->id]);
    ServerConnection::factory()->create(); // other user

    $this->actingAs($this->user)
        ->getJson('/api/servers')
        ->assertOk()
        ->assertJsonCount(2);
});

it('deletes server connection', function () {
    $server = ServerConnection::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/servers/{$server->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('server_connections', ['id' => $server->id]);
});

it('checks server health', function () {
    Http::fake([
        '*/api/v1/healthcheck' => Http::response(['status' => 'ok']),
    ]);

    $server = ServerConnection::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->getJson("/api/servers/{$server->id}/health")
        ->assertOk()
        ->assertJsonPath('healthy', true);
});

it('prevents accessing other users server', function () {
    $server = ServerConnection::factory()->create();

    $this->actingAs($this->user)
        ->getJson("/api/servers/{$server->id}")
        ->assertForbidden();
});
```

- [ ] **Step 2: Create DeployTest**

```php
<?php

use App\Enums\ProjectStatusEnum;
use App\Jobs\DeployToCoolifyJob;
use App\Models\Project;
use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = ServerConnection::factory()->create(['user_id' => $this->user->id, 'status' => 'active']);
});

it('deploys project and dispatches job', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Exported,
        'github_repo_url' => 'https://github.com/user/repo',
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/deploy", ['server_id' => $this->server->id])
        ->assertStatus(202);

    Queue::assertPushed(DeployToCoolifyJob::class);
});

it('rejects deploy for non-exported project', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/deploy", ['server_id' => $this->server->id])
        ->assertStatus(422);
});

it('rejects deploy with inactive server', function () {
    Queue::fake();

    $server = ServerConnection::factory()->provisioning()->create(['user_id' => $this->user->id]);
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Exported,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/deploy", ['server_id' => $server->id])
        ->assertStatus(422);
});

it('returns deployed status with url', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Deployed,
        'deploy_url' => 'https://app.example.com',
        'deployed_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/deploy/status")
        ->assertOk()
        ->assertJsonPath('status', 'deployed')
        ->assertJsonPath('deploy_url', 'https://app.example.com');
});

it('tears down deployment', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Deployed,
        'coolify_app_id' => 'app-uuid',
    ]);

    // Mock Coolify delete (may fail silently)
    \Illuminate\Support\Facades\Http::fake();

    $this->actingAs($this->user)
        ->deleteJson("/api/projects/{$project->id}/deploy")
        ->assertNoContent();

    $project->refresh();
    expect($project->status)->toBe(ProjectStatusEnum::Exported);
    expect($project->deploy_url)->toBeNull();
});

it('prevents deploying other users project', function () {
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $other->id, 'status' => ProjectStatusEnum::Exported]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/deploy", ['server_id' => $this->server->id])
        ->assertForbidden();
});
```

- [ ] **Step 3: Create HetznerServiceTest**

```php
<?php

use App\Services\Deploy\HetznerService;
use Illuminate\Support\Facades\Http;

it('creates server via hetzner api', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers' => Http::response([
            'server' => [
                'id' => 12345,
                'public_net' => ['ipv4' => ['ip' => '1.2.3.4']],
                'status' => 'initializing',
            ],
        ]),
    ]);

    $service = new HetznerService();
    $result = $service->createServer('test_key', 'my-server');

    expect($result['server_id'])->toBe('12345');
    expect($result['server_ip'])->toBe('1.2.3.4');

    Http::assertSent(fn($r) => str_contains($r->url(), '/v1/servers') && $r->method() === 'POST');
});

it('gets server status', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers/*' => Http::response([
            'server' => [
                'id' => 12345,
                'status' => 'running',
                'public_net' => ['ipv4' => ['ip' => '1.2.3.4']],
            ],
        ]),
    ]);

    $service = new HetznerService();
    $result = $service->getServer('test_key', '12345');

    expect($result['status'])->toBe('running');
});

it('throws on invalid api key', function () {
    Http::fake([
        'api.hetzner.cloud/*' => Http::response(['error' => ['message' => 'unauthorized']], 401),
    ]);

    $service = new HetznerService();
    $service->createServer('bad_key', 'test');
})->throws(\RuntimeException::class, 'Invalid Hetzner API key');
```

- [ ] **Step 4: Create CoolifyServiceTest**

```php
<?php

use App\Models\ServerConnection;
use App\Services\Deploy\CoolifyService;
use Illuminate\Support\Facades\Http;

it('returns true for healthy coolify', function () {
    Http::fake([
        '*/api/v1/healthcheck' => Http::response(['status' => 'ok']),
    ]);

    $service = new CoolifyService();
    expect($service->healthcheck('http://1.2.3.4:8000', 'key'))->toBeTrue();
});

it('returns false for unreachable coolify', function () {
    Http::fake([
        '*/api/v1/healthcheck' => Http::response([], 500),
    ]);

    $service = new CoolifyService();
    expect($service->healthcheck('http://1.2.3.4:8000', 'key'))->toBeFalse();
});

it('creates application', function () {
    Http::fake([
        '*/api/v1/applications' => Http::response(['id' => 1, 'uuid' => 'app-uuid', 'fqdn' => 'http://app.test']),
    ]);

    $conn = ServerConnection::factory()->create();
    $service = new CoolifyService();
    $result = $service->createApplication($conn, 'https://github.com/user/repo');

    expect($result['uuid'])->toBe('app-uuid');
});

it('creates database', function () {
    Http::fake([
        '*/api/v1/databases/postgresql' => Http::response([
            'id' => 1, 'db_name' => 'mydb', 'db_user' => 'postgres', 'db_password' => 'secret',
        ]),
    ]);

    $conn = ServerConnection::factory()->create();
    $service = new CoolifyService();
    $result = $service->createDatabase($conn, 'mydb');

    expect($result['db_name'])->toBe('mydb');
});

it('triggers deploy', function () {
    Http::fake([
        '*/api/v1/applications/*/deploy' => Http::response(['deployment_uuid' => 'deploy-123']),
    ]);

    $conn = ServerConnection::factory()->create();
    $service = new CoolifyService();
    $result = $service->deploy($conn, 'app-uuid');

    expect($result['deployment_uuid'])->toBe('deploy-123');
});
```

- [ ] **Step 5: Run all tests**

```bash
php artisan test
```

Expected: All existing + new tests pass.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add server, deploy, Hetzner, Coolify tests"
```

---

## Task 6: Deploy UI (React)

**Files:**
- Create: `resources/js/pages/Deploy/DeployPage.jsx`
- Create: `resources/js/pages/Deploy/ServerSetup.jsx`
- Create: `resources/js/pages/Deploy/DeployProgress.jsx`
- Modify: `resources/js/app.jsx`
- Modify: `resources/js/pages/ProjectList.jsx`
- Modify: `resources/js/pages/Preview/PreviewToolbar.jsx`

- [ ] **Step 1: Create ServerSetup component**

`resources/js/pages/Deploy/ServerSetup.jsx` — "Connect a Server" page with two cards:

Card 1: "Create with Hetzner" — API key input (password), name, server type dropdown. On submit: POST /api/servers. Shows provisioning progress with status polling. After server reaches `installing` status: prompt user to visit Coolify dashboard, enter API key.

Card 2: "Connect Existing Coolify" — URL input, API key input (password). On submit: POST /api/servers. Shows success or error.

Props: `onServerConnected` callback.

- [ ] **Step 2: Create DeployProgress component**

`resources/js/pages/Deploy/DeployProgress.jsx` — deploy controls and status.

Props: `projectId`, `server` (server connection object)

Shows: server info card, "Deploy" button, progress stepper (Creating app → DB → Env → Building → Live), terminal-style log area, live URL when deployed.

On "Deploy" click: POST /api/projects/{id}/deploy with server_id. Poll GET /api/projects/{id}/deploy/status every 3s.

- [ ] **Step 3: Create DeployPage**

`resources/js/pages/Deploy/DeployPage.jsx` — main page orchestrator.

On mount: GET /api/servers to check if user has active server. Also GET /api/projects/{id}/deploy/status for current deploy state.

If no active server → render ServerSetup.
If has server → render DeployProgress.

Uses AppLayout with activePage="deployments".

- [ ] **Step 4: Update app.jsx**

Add import and route:
```jsx
import DeployPage from './pages/Deploy/DeployPage';
<Route path="/projects/:projectId/deploy" element={<DeployPage />} />
```

- [ ] **Step 5: Update ProjectList**

Add "Deploy" link for exported projects:
```jsx
{project.status === 'exported' && (
    <Link to={`/projects/${project.id}/deploy`} className="text-tertiary text-sm font-medium">Deploy</Link>
)}
```

- [ ] **Step 6: Update PreviewToolbar**

Add "Deploy" button (next to export dropdown) for exported projects. Links to `/projects/{projectId}/deploy`.

- [ ] **Step 7: Verify build**

```bash
npm run build
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add Deploy UI (ServerSetup, DeployProgress, DeployPage)"
```

---

## Task 7: Final Verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 2: Verify build**

```bash
npm run build
```

- [ ] **Step 3: Verify routes**

```bash
php artisan route:list 2>/dev/null | grep -E "server|deploy"
```

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore: Phase 4 complete — BYOS Deploy via Coolify"
```

---

## Summary

| Task | Description | Key Files |
|------|-------------|-----------|
| 1 | Migration + ServerConnection model + factory | Migration, model, factory |
| 2 | HetznerService + CoolifyService | Deploy service layer |
| 3 | ProvisionServerJob + DeployToCoolifyJob | Queued async jobs |
| 4 | Controllers + routes | ServerController, DeployController, api.php |
| 5 | Backend tests (4 test files) | Server, deploy, Hetzner, Coolify tests |
| 6 | Deploy UI (React) | DeployPage, ServerSetup, DeployProgress |
| 7 | Final verification | Tests + build |
