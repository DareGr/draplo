# Phase 3B+3D — GitHub OAuth + Export + ZIP Download Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add GitHub OAuth login, GitHub repo export via Git Data API, and ZIP download so users can authenticate with GitHub, push their generated scaffold to a private repo, or download it as a ZIP file.

**Architecture:** Laravel Socialite for GitHub OAuth. GitHubService wraps the Git Data API (blob → tree → commit → ref) to push files in ~5 API calls. PushToGitHubJob runs async via Redis queue. ZipExportController generates ZIP synchronously. Export UI in the Preview page toolbar with dropdown and post-export instructions.

**Tech Stack:** Laravel 12, Socialite, GitHub API v3, ZipArchive, React 18

**Spec:** `docs/superpowers/specs/2026-03-22-phase3bd-github-export-zip-design.md`

---

## File Structure

### New files
```
app/Http/Controllers/Auth/GitHubAuthController.php    — OAuth redirect + callback
app/Http/Controllers/Export/GitHubExportController.php — trigger export, check status
app/Http/Controllers/Export/ZipExportController.php    — synchronous ZIP download
app/Services/GitHubService.php                         — Git Data API (create repo, push files)
app/Jobs/PushToGitHubJob.php                           — queued GitHub export
resources/js/pages/AuthCallback.jsx                    — reads token from URL fragment
resources/js/pages/Preview/ExportDropdown.jsx          — export button dropdown
resources/js/pages/Preview/WhatNextCard.jsx            — post-export instructions
tests/Feature/GitHubAuthTest.php
tests/Feature/ExportTest.php
tests/Feature/GitHubServiceTest.php
```

### Files to modify
```
app/Enums/ProjectStatusEnum.php     — add Exporting case
config/services.php                 — add github OAuth config
.env.example                        — add GITHUB_CLIENT_ID, SECRET, REDIRECT
routes/web.php                      — add OAuth routes, update SPA catch-all regex
routes/api.php                      — add export endpoints
resources/js/app.jsx                — add /auth/callback route
resources/js/pages/Preview/PreviewToolbar.jsx  — replace Export with ExportDropdown
resources/js/pages/Preview/PreviewLayout.jsx   — add export state + WhatNextCard
```

---

## Task 1: Socialite Install + ProjectStatusEnum + Config

**Files:**
- Modify: `app/Enums/ProjectStatusEnum.php`
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] **Step 1: Install Socialite**

```bash
cd /c/draplo
composer require laravel/socialite --ignore-platform-reqs
```

- [ ] **Step 2: Add Exporting to ProjectStatusEnum**

In `app/Enums/ProjectStatusEnum.php`, add after `Exported`:
```php
case Exporting = 'exporting';
```

- [ ] **Step 3: Add GitHub OAuth config to config/services.php**

Append to the return array:
```php
'github' => [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect' => env('GITHUB_REDIRECT_URL', '/auth/github/callback'),
],
```

- [ ] **Step 4: Update .env.example**

Add:
```env
# GitHub OAuth
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URL=/auth/github/callback
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: install Socialite, add Exporting status, GitHub OAuth config"
```

---

## Task 2: GitHub OAuth Controller + Routes

**Files:**
- Create: `app/Http/Controllers/Auth/GitHubAuthController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create GitHubAuthController**

Create `app/Http/Controllers/Auth/GitHubAuthController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GitHubAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['repo', 'user:email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $user = User::updateOrCreate(
            ['github_id' => (string) $githubUser->getId()],
            [
                'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'email' => $githubUser->getEmail(),
                'github_username' => $githubUser->getNickname(),
                'avatar_url' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
            ]
        );

        $token = $user->createToken('github-auth')->plainTextToken;

        return redirect("/auth/callback#token={$token}");
    }
}
```

- [ ] **Step 2: Update routes/web.php**

Read the file first. Add the OAuth routes before the SPA catch-all, and update the catch-all regex to exclude `auth`:

```php
use App\Http\Controllers\Auth\GitHubAuthController;

// GitHub OAuth
Route::get('/auth/github', [GitHubAuthController::class, 'redirect']);
Route::get('/auth/github/callback', [GitHubAuthController::class, 'callback']);

// Update the SPA catch-all regex to:
->where('any', '^(?!api|dev|auth|sanctum|horizon).*$');
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: add GitHubAuthController with OAuth redirect and callback"
```

---

## Task 3: GitHubService

**Files:**
- Create: `app/Services/GitHubService.php`

- [ ] **Step 1: Create GitHubService**

Create `app/Services/GitHubService.php`:

```php
<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private const API_BASE = 'https://api.github.com';

    public function export(User $user, Project $project, string $repoName): array
    {
        $token = $user->github_token;

        // Step 1: Create repo
        $repoResponse = $this->githubRequest($token, 'POST', '/user/repos', [
            'name' => $repoName,
            'description' => $project->description ?? "Generated by Draplo",
            'private' => true,
            'auto_init' => false,
        ]);

        $repoFullName = $repoResponse['full_name'];
        $repoUrl = $repoResponse['html_url'];

        // Step 2: Create blobs for each file
        $blobs = [];
        foreach ($project->generation_output as $file) {
            $blobResponse = $this->githubRequest($token, 'POST', "/repos/{$repoFullName}/git/blobs", [
                'content' => $file['content'],
                'encoding' => 'utf-8',
            ]);
            $blobs[] = [
                'path' => $file['path'],
                'mode' => '100644',
                'type' => 'blob',
                'sha' => $blobResponse['sha'],
            ];
        }

        // Step 3: Create tree
        $treeResponse = $this->githubRequest($token, 'POST', "/repos/{$repoFullName}/git/trees", [
            'tree' => $blobs,
        ]);

        // Step 4: Create commit
        $commitResponse = $this->githubRequest($token, 'POST', "/repos/{$repoFullName}/git/commits", [
            'message' => 'Initial scaffold generated by Draplo',
            'tree' => $treeResponse['sha'],
        ]);

        // Step 5: Create ref (main branch)
        $this->githubRequest($token, 'POST', "/repos/{$repoFullName}/git/refs", [
            'ref' => 'refs/heads/main',
            'sha' => $commitResponse['sha'],
        ]);

        return [
            'repo_url' => $repoUrl,
            'repo_name' => $repoFullName,
        ];
    }

    private function githubRequest(string $token, string $method, string $path, array $data = [], int $retries = 0): array
    {
        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github.v3+json',
            ])
            ->{strtolower($method)}(self::API_BASE . $path, $data);

        // Retry on rate limit
        if (in_array($response->status(), [403, 429]) && $retries < 3) {
            $delay = pow(2, $retries); // 1s, 2s, 4s
            sleep($delay);
            Log::warning("GitHub API rate limit, retrying in {$delay}s", ['path' => $path, 'retry' => $retries + 1]);
            return $this->githubRequest($token, $method, $path, $data, $retries + 1);
        }

        if ($response->status() === 401) {
            throw new \RuntimeException('GitHub token expired or revoked. Please re-login via GitHub.');
        }

        if ($response->status() === 422) {
            $message = $response->json('message') ?? 'Validation failed';
            if (str_contains($message, 'name already exists')) {
                throw new \RuntimeException('Repository name already exists on your GitHub account.');
            }
            throw new \RuntimeException("GitHub API error: {$message}");
        }

        if (!$response->successful()) {
            throw new \RuntimeException("GitHub API error ({$response->status()}): " . $response->body());
        }

        return $response->json();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "feat: add GitHubService with Git Data API (blob, tree, commit, ref)"
```

---

## Task 4: PushToGitHubJob + Export Controllers + Routes

**Files:**
- Create: `app/Jobs/PushToGitHubJob.php`
- Create: `app/Http/Controllers/Export/GitHubExportController.php`
- Create: `app/Http/Controllers/Export/ZipExportController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create PushToGitHubJob**

Create `app/Jobs/PushToGitHubJob.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushToGitHubJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(
        public Project $project,
        public User $user,
        public string $repoName,
    ) {}

    public function handle(GitHubService $githubService): void
    {
        try {
            $result = $githubService->export($this->user, $this->project, $this->repoName);

            $this->project->update([
                'github_repo_url' => $result['repo_url'],
                'github_repo_name' => $result['repo_name'],
                'status' => ProjectStatusEnum::Exported,
                'exported_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Revert to generated so user can retry
            $this->project->update(['status' => ProjectStatusEnum::Generated]);
            Log::error('GitHub export failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 2: Create GitHubExportController**

Create `app/Http/Controllers/Export/GitHubExportController.php`:

```php
<?php

namespace App\Http\Controllers\Export;

use App\Enums\ProjectStatusEnum;
use App\Http\Controllers\Controller;
use App\Jobs\PushToGitHubJob;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubExportController extends Controller
{
    public function export(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if (!in_array($project->status, [ProjectStatusEnum::Generated, ProjectStatusEnum::Exported])) {
            return response()->json(['message' => 'Project must be generated before exporting.'], 422);
        }

        $user = $request->user();
        if (!$user->github_token) {
            return response()->json(['message' => 'GitHub not connected. Please login via GitHub first.'], 422);
        }

        $repoName = $request->input('repo_name', $project->slug);

        $project->update(['status' => ProjectStatusEnum::Exporting]);

        PushToGitHubJob::dispatch($project, $user, $repoName);

        return response()->json([
            'status' => 'exporting',
            'message' => 'Export started.',
        ], 202);
    }

    public function status(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status === ProjectStatusEnum::Exporting) {
            return response()->json(['status' => 'exporting']);
        }

        if ($project->status === ProjectStatusEnum::Exported) {
            return response()->json([
                'status' => 'exported',
                'github_repo_url' => $project->github_repo_url,
                'github_repo_name' => $project->github_repo_name,
                'exported_at' => $project->exported_at?->toISOString(),
            ]);
        }

        return response()->json(['status' => 'pending']);
    }
}
```

- [ ] **Step 3: Create ZipExportController**

Create `app/Http/Controllers/Export/ZipExportController.php`:

```php
<?php

namespace App\Http\Controllers\Export;

use App\Enums\ProjectStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ZipExportController extends Controller
{
    public function download(Project $project): BinaryFileResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if (!in_array($project->status, [ProjectStatusEnum::Generated, ProjectStatusEnum::Exported])) {
            abort(404, 'No generated output available.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'draplo_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        foreach ($project->generation_output as $file) {
            $zip->addFromString($file['path'], $file['content']);
        }

        $zip->close();

        $project->update(['exported_at' => now()]);

        return response()->download($zipPath, "{$project->slug}.zip")
            ->deleteFileAfterSend(true);
    }
}
```

- [ ] **Step 4: Add export routes to routes/api.php**

Read the file first. Add inside the `auth:sanctum` group:

```php
use App\Http\Controllers\Export\GitHubExportController;
use App\Http\Controllers\Export\ZipExportController;

// Export
Route::post('/projects/{project}/export/github', [GitHubExportController::class, 'export']);
Route::get('/projects/{project}/export/status', [GitHubExportController::class, 'status']);
Route::get('/projects/{project}/export/zip', [ZipExportController::class, 'download']);
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add PushToGitHubJob, GitHubExportController, ZipExportController, export routes"
```

---

## Task 5: Backend Tests

**Files:**
- Create: `tests/Feature/GitHubAuthTest.php`
- Create: `tests/Feature/ExportTest.php`
- Create: `tests/Feature/GitHubServiceTest.php`

- [ ] **Step 1: Create GitHubAuthTest**

```php
<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('redirects to GitHub for OAuth', function () {
    $this->get('/auth/github')
        ->assertRedirect();
});

it('creates new user from GitHub callback', function () {
    $githubUser = new SocialiteUser();
    $githubUser->id = '99999';
    $githubUser->name = 'Test User';
    $githubUser->email = 'test@github.com';
    $githubUser->nickname = 'testuser';
    $githubUser->avatar = 'https://github.com/avatar.png';
    $githubUser->token = 'gh_test_token';

    Socialite::shouldReceive('driver->user')->andReturn($githubUser);

    $this->get('/auth/github/callback')
        ->assertRedirect()
        ->assertRedirectContains('/auth/callback#token=');

    $this->assertDatabaseHas('users', [
        'github_id' => '99999',
        'github_username' => 'testuser',
        'email' => 'test@github.com',
    ]);
});

it('logs in existing user and updates token', function () {
    $user = User::factory()->create([
        'github_id' => '88888',
        'github_token' => 'old_token',
    ]);

    $githubUser = new SocialiteUser();
    $githubUser->id = '88888';
    $githubUser->name = $user->name;
    $githubUser->email = $user->email;
    $githubUser->nickname = 'updated_user';
    $githubUser->avatar = 'https://new-avatar.png';
    $githubUser->token = 'new_token';

    Socialite::shouldReceive('driver->user')->andReturn($githubUser);

    $this->get('/auth/github/callback')
        ->assertRedirect();

    $user->refresh();
    expect($user->github_username)->toBe('updated_user');
    expect(User::where('github_id', '88888')->count())->toBe(1);
});
```

- [ ] **Step 2: Create ExportTest**

```php
<?php

use App\Enums\ProjectStatusEnum;
use App\Jobs\PushToGitHubJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create(['github_token' => 'test_token']);
});

it('triggers GitHub export and returns 202', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => [['path' => 'test.md', 'content' => '#']],
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/export/github", ['repo_name' => 'test-repo'])
        ->assertStatus(202)
        ->assertJsonPath('status', 'exporting');

    $project->refresh();
    expect($project->status)->toBe(ProjectStatusEnum::Exporting);

    Queue::assertPushed(PushToGitHubJob::class);
});

it('rejects export when status is not generated', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Draft,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/export/github")
        ->assertStatus(422);

    Queue::assertNothingPushed();
});

it('rejects export when user has no github token', function () {
    $user = User::factory()->create(['github_token' => null]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'status' => ProjectStatusEnum::Generated,
    ]);

    $this->actingAs($user)
        ->postJson("/api/projects/{$project->id}/export/github")
        ->assertStatus(422)
        ->assertJsonPath('message', 'GitHub not connected. Please login via GitHub first.');
});

it('returns exported status with repo info', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Exported,
        'github_repo_url' => 'https://github.com/user/repo',
        'github_repo_name' => 'user/repo',
        'exported_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/export/status")
        ->assertOk()
        ->assertJsonPath('status', 'exported')
        ->assertJsonPath('github_repo_url', 'https://github.com/user/repo');
});

it('returns pending status when not exported', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/export/status")
        ->assertOk()
        ->assertJsonPath('status', 'pending');
});

it('downloads ZIP with correct headers', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'slug' => 'test-project',
        'generation_output' => [
            ['path' => 'CLAUDE.md', 'content' => '# Test'],
            ['path' => 'PROJECT.md', 'content' => '# Project'],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->get("/api/projects/{$project->id}/export/zip");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/zip');
    expect($response->headers->get('content-disposition'))->toContain('test-project.zip');
});

it('rejects ZIP when not generated', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
    ]);

    $this->actingAs($this->user)
        ->get("/api/projects/{$project->id}/export/zip")
        ->assertNotFound();
});

it('prevents exporting another users project', function () {
    $other = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $other->id,
        'status' => ProjectStatusEnum::Generated,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/export/github")
        ->assertForbidden();
});

it('allows re-export from exported status', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Exported,
        'generation_output' => [['path' => 'test.md', 'content' => '#']],
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/export/github", ['repo_name' => 'new-repo'])
        ->assertStatus(202);

    Queue::assertPushed(PushToGitHubJob::class);
});
```

- [ ] **Step 3: Create GitHubServiceTest**

```php
<?php

use App\Models\Project;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Http;

it('creates repo and pushes files via Git Data API', function () {
    Http::fake([
        'api.github.com/user/repos' => Http::response([
            'full_name' => 'testuser/test-repo',
            'html_url' => 'https://github.com/testuser/test-repo',
        ]),
        'api.github.com/repos/testuser/test-repo/git/blobs' => Http::response(['sha' => 'blob123']),
        'api.github.com/repos/testuser/test-repo/git/trees' => Http::response(['sha' => 'tree123']),
        'api.github.com/repos/testuser/test-repo/git/commits' => Http::response(['sha' => 'commit123']),
        'api.github.com/repos/testuser/test-repo/git/refs' => Http::response(['ref' => 'refs/heads/main']),
    ]);

    $user = User::factory()->create(['github_token' => 'test_token']);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'generation_output' => [
            ['path' => 'CLAUDE.md', 'content' => '# Context'],
            ['path' => 'PROJECT.md', 'content' => '# Project'],
        ],
    ]);

    $service = new GitHubService();
    $result = $service->export($user, $project, 'test-repo');

    expect($result['repo_url'])->toBe('https://github.com/testuser/test-repo');
    expect($result['repo_name'])->toBe('testuser/test-repo');

    Http::assertSent(fn($r) => str_contains($r->url(), '/user/repos'));
    Http::assertSent(fn($r) => str_contains($r->url(), '/git/blobs'));
    Http::assertSent(fn($r) => str_contains($r->url(), '/git/trees'));
    Http::assertSent(fn($r) => str_contains($r->url(), '/git/commits'));
    Http::assertSent(fn($r) => str_contains($r->url(), '/git/refs'));
});

it('throws on expired token (401)', function () {
    Http::fake([
        'api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401),
    ]);

    $user = User::factory()->create(['github_token' => 'expired']);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'generation_output' => [['path' => 'test.md', 'content' => '#']],
    ]);

    $service = new GitHubService();
    $service->export($user, $project, 'test-repo');
})->throws(\RuntimeException::class, 'GitHub token expired');

it('throws on repo name conflict (422)', function () {
    Http::fake([
        'api.github.com/user/repos' => Http::response([
            'message' => 'Repository creation failed.',
            'errors' => [['message' => 'name already exists on this account']],
        ], 422),
    ]);

    $user = User::factory()->create(['github_token' => 'valid']);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'generation_output' => [['path' => 'test.md', 'content' => '#']],
    ]);

    $service = new GitHubService();
    $service->export($user, $project, 'existing-repo');
})->throws(\RuntimeException::class, 'already exists');
```

- [ ] **Step 4: Run tests**

```bash
php artisan test
```

Expected: All existing + new tests pass.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add GitHub auth, export, ZIP download, and GitHubService tests"
```

---

## Task 6: Export UI Components (React)

**Files:**
- Create: `resources/js/pages/Preview/ExportDropdown.jsx`
- Create: `resources/js/pages/Preview/WhatNextCard.jsx`
- Create: `resources/js/pages/AuthCallback.jsx`
- Modify: `resources/js/app.jsx`
- Modify: `resources/js/pages/Preview/PreviewToolbar.jsx`
- Modify: `resources/js/pages/Preview/PreviewLayout.jsx`

- [ ] **Step 1: Create AuthCallback page**

Create `resources/js/pages/AuthCallback.jsx`:

```jsx
import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

export default function AuthCallback() {
    const navigate = useNavigate();

    useEffect(() => {
        const hash = window.location.hash;
        const match = hash.match(/token=([^&]+)/);

        if (match) {
            localStorage.setItem('auth_token', match[1]);
            navigate('/templates', { replace: true });
        } else {
            navigate('/auth/github', { replace: true });
        }
    }, [navigate]);

    return (
        <div className="min-h-screen flex items-center justify-center bg-background">
            <div className="text-primary font-mono">Authenticating...</div>
        </div>
    );
}
```

- [ ] **Step 2: Create ExportDropdown**

Create `resources/js/pages/Preview/ExportDropdown.jsx`:

A dropdown button with two options:
- "Push to GitHub" — shows repo name input modal, triggers export, polls status
- "Download ZIP" — direct download

Props: `projectId`, `projectSlug`, `projectStatus`, `userHasGithubToken`, `onExportComplete`

The component manages its own dropdown open/close state, repo name input, and export-in-progress state. Uses `api` from `../../api` for API calls.

For ZIP download: use `window.open` with the API URL + token as query param (since it's a file download, can't use Axios easily). Alternative: `api.get(..., { responseType: 'blob' })` then create object URL.

For GitHub export: POST to API, then poll status every 2s. When exported, call `onExportComplete(data)`.

Styling: dropdown panel `bg-surface-container-high rounded-xl border border-outline-variant/10 shadow-xl p-3`, items with icon + text + description.

- [ ] **Step 3: Create WhatNextCard**

Create `resources/js/pages/Preview/WhatNextCard.jsx`:

Props: `repoUrl`, `repoName`, `projectSlug`

Card overlay shown after successful GitHub export:
- Success icon `check_circle` in green
- Title: "Your scaffold is ready!"
- Repo URL as clickable link
- Terminal card with clone/setup instructions (monospace, bg-surface-container-lowest)
- "Open on GitHub" button (primary, links to repoUrl in new tab)
- "Close" button (secondary)

- [ ] **Step 4: Update app.jsx**

Add import and route for AuthCallback:
```jsx
import AuthCallback from './pages/AuthCallback';

// Add before catch-all:
<Route path="/auth/callback" element={<AuthCallback />} />
```

- [ ] **Step 5: Update PreviewToolbar**

Read the file first. Replace the disabled Export button with the ExportDropdown component. Pass required props from PreviewLayout.

- [ ] **Step 6: Update PreviewLayout**

Read the file first. Add:
- `exportStatus` state (null | 'exporting' | 'exported')
- `exportData` state (null | { github_repo_url, github_repo_name })
- Pass export props to PreviewToolbar/ExportDropdown
- When export completes: set exportData, show WhatNextCard
- WhatNextCard rendered as an overlay or below the toolbar

- [ ] **Step 7: Verify build**

```bash
npm run build
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add export UI (ExportDropdown, WhatNextCard, AuthCallback), wire into Preview"
```

---

## Task 7: Final Verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 2: Verify build**

```bash
npm run build
```

- [ ] **Step 3: Verify routes**

```bash
php artisan route:list 2>/dev/null | grep -E "auth|export|zip"
```

Expected: OAuth routes in web, export routes in api.

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore: Phase 3B+3D complete — GitHub OAuth, export, ZIP download"
```

---

## Summary

| Task | Description | Key Files |
|------|-------------|-----------|
| 1 | Socialite + Exporting enum + config | ProjectStatusEnum, services.php, .env.example |
| 2 | GitHub OAuth controller + routes | GitHubAuthController, web.php |
| 3 | GitHubService (Git Data API) | GitHubService.php |
| 4 | Jobs + export controllers + routes | PushToGitHubJob, controllers, api.php |
| 5 | Backend tests | GitHubAuthTest, ExportTest, GitHubServiceTest |
| 6 | Export UI (React) | ExportDropdown, WhatNextCard, AuthCallback |
| 7 | Final verification | Tests + build |
