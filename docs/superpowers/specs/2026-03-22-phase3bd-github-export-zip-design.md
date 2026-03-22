# Phase 3B+3D — GitHub OAuth + Export + ZIP Download Design Spec

**Date:** 2026-03-22
**Status:** Approved
**Scope:** GitHub OAuth login, GitHub repo export via Git Data API, ZIP download, export UI in preview page, post-export instructions
**Depends on:** Phase 3A (Preview UI) — completed

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Export gate | Free for all authenticated users | Stripe skipped. Payment gate can wrap export endpoints later. |
| Dev-mode auth | Kept alongside GitHub OAuth | Dev-mode stays for local development (APP_ENV=local). Tests unaffected. |
| GitHub API strategy | Git Data API (blobs + tree + commit) | Contents API needs one request per file (30+). Git Data API batches into ~5 requests. |
| GitHub export execution | Queued job (PushToGitHubJob) | 5-10s for API calls, would time out synchronous HTTP. Same polling pattern as generation. |
| ZIP download | Synchronous endpoint | ZIP generation from in-memory data takes <1s. No queue needed. |
| Spec scope | 3B + 3D combined | Both are export mechanisms, share UI surface (export dropdown), small enough for one spec. |

---

## 1. GitHub OAuth

**Package:** Laravel Socialite (`laravel/socialite`)

### OAuth Flow

1. User clicks "Sign In" → `GET /auth/github` → Socialite redirects to GitHub
2. GitHub authorizes → redirects to `GET /auth/github/callback`
3. Controller receives GitHub user profile (id, name, email, username, avatar, token)
4. **Existing user** (matching `github_id`): log in, update `github_token`, `github_username`, `avatar_url`
5. **New user**: create User with GitHub profile data, plan `free`
6. Create Sanctum token
7. Redirect to `/auth/callback#token={sanctumToken}` (SPA reads from URL fragment)

### React Auth Callback

New route `/auth/callback` in React:
1. Read token from URL hash fragment
2. Store in localStorage
3. Redirect to `/templates`

### Scopes

Request `repo` (create private repos, push files) and `user:email` (get email address).

### Config

Add to `config/services.php`:
```php
'github' => [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect' => env('GITHUB_REDIRECT_URL', '/auth/github/callback'),
],
```

Add to `.env.example`:
```env
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URL=/auth/github/callback
```

### Dev-Mode Auth Coexistence

Dev-mode auth (`GET /dev/login`) remains active when `APP_ENV=local`. In production, only GitHub OAuth is available. The React SPA's `ensureAuth()` in `api.js` should:
1. Check localStorage for token
2. If no token and dev mode: auto-login via `/dev/login`
3. If no token and production: redirect to `/auth/github`

### Controller

**`app/Http/Controllers/Auth/GitHubAuthController.php`:**

```php
public function redirect(): RedirectResponse
{
    return Socialite::driver('github')->scopes(['repo', 'user:email'])->redirect();
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
```

### Routes (web.php)

```php
Route::get('/auth/github', [GitHubAuthController::class, 'redirect']);
Route::get('/auth/github/callback', [GitHubAuthController::class, 'callback']);
```

**Important:** Update the SPA catch-all regex to exclude `/auth`: change `^(?!api|dev|sanctum|horizon).*$` to `^(?!api|dev|auth|sanctum|horizon).*$`. Otherwise the catch-all would intercept `/auth/github/callback` before Laravel's route can handle it.

**CSRF note:** Both routes are GET requests, so Laravel's CSRF middleware does not apply. Socialite handles OAuth CSRF via the `state` parameter automatically.

---

## 2. GitHubService + Export

### GitHubService

**`app/Services/GitHubService.php`** — creates private repo and pushes files via Git Data API.

**Method: `export(User $user, Project $project, string $repoName): array`**

Steps:
1. **Create repo:** `POST https://api.github.com/user/repos`
   - Body: `{ name: repoName, description: project.description, private: true, auto_init: false }`
   - Auth: `Authorization: Bearer {user.github_token}`
   - Returns: repo full_name, html_url

2. **Create blobs** for each file: `POST /repos/{owner}/{repo}/git/blobs`
   - Body: `{ content: fileContent, encoding: 'utf-8' }`
   - Returns: SHA per blob
   - Loop through all files in `project.generation_output`

3. **Create tree:** `POST /repos/{owner}/{repo}/git/trees`
   - Body: `{ tree: [{ path, mode: '100644', type: 'blob', sha }] }`
   - Returns: tree SHA

4. **Create commit:** `POST /repos/{owner}/{repo}/git/commits`
   - Body: `{ message: 'Initial scaffold generated by Draplo', tree: treeSHA }`
   - Returns: commit SHA

5. **Create ref:** `POST /repos/{owner}/{repo}/git/refs`
   - Body: `{ ref: 'refs/heads/main', sha: commitSHA }`

6. **Return:** `{ repo_url: html_url, repo_name: full_name }`

**Error handling:**
- 401: token expired/revoked → throw with "GitHub token expired. Please re-login."
- 422 (repo name taken): throw with "Repository name already exists."
- 403/429 (rate limit): retry with exponential backoff (1s, 2s, 4s), max 3 retries per request. Critical for blob creation loop (30+ sequential requests).
- Other errors: throw with GitHub API error message

**HTTP config:** timeout 30s, connect timeout 10s. All requests use `Accept: application/vnd.github.v3+json`.

**Scope note:** The `repo` scope grants full access to all user repos. This is the minimum GitHub scope for creating private repos — GitHub does not offer a narrower scope. Future improvement: migrate to a GitHub App for granular permissions.

### PushToGitHubJob

**`app/Jobs/PushToGitHubJob.php`** — queued job.

```
$timeout = 60, $tries = 1
```

1. Call `GitHubService::export(user, project, repoName)`
2. Update project: `github_repo_url`, `github_repo_name`, `status: exported`, `exported_at: now()`
3. On failure: log error, do NOT change project status (stays `generated`, user can retry)

### API Endpoints

**`POST /api/projects/{project}/export/github`**
- Input: `{ repo_name?: string }` (defaults to project slug)
- Validates: ownership, status is `generated` or `exported` (re-export allowed), user has `github_token`
- Sets project status to `exporting` (new enum value — add `Exporting` to `ProjectStatusEnum`)
- Dispatches PushToGitHubJob
- Returns: 202 `{ status: 'exporting', message: 'Export started' }`

**`GET /api/projects/{project}/export/status`**
- Returns export status:
  - If `exporting`: `{ status: 'exporting' }` (job in progress)
  - If `exported`: `{ status: 'exported', github_repo_url, github_repo_name, exported_at }`
  - Otherwise: `{ status: 'pending' }`

**Controller:** `app/Http/Controllers/Export/GitHubExportController.php`

---

## 3. ZIP Download

**`GET /api/projects/{project}/export/zip`**
- Validates: ownership, status is `generated`
- Creates ZipArchive in temp directory
- Adds each file from `generation_output` with its path
- Returns streamed download: `Content-Type: application/zip`, `Content-Disposition: attachment; filename="{slug}.zip"`
- Cleans up temp file after response
- Sets `exported_at` on the project (does NOT change status to `exported` — ZIP is a lighter export)

**Controller:** `app/Http/Controllers/Export/ZipExportController.php`

```php
public function download(Project $project): StreamedResponse
{
    if ($project->user_id !== auth()->id()) {
        abort(403);
    }

    if ($project->status !== ProjectStatusEnum::Generated && $project->status !== ProjectStatusEnum::Exported) {
        return response()->json(['message' => 'No generated output available.'], 404);
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'draplo_') . '.zip';
    $zip = new \ZipArchive();
    $zip->open($zipPath, \ZipArchive::CREATE);

    foreach ($project->generation_output as $file) {
        $zip->addFromString($file['path'], $file['content']);
    }

    $zip->close();

    $project->update(['exported_at' => now()]);

    return response()->download($zipPath, "{$project->slug}.zip")->deleteFileAfterSend(true);
}
```

---

## 4. Export UI

### ExportDropdown

**`resources/js/pages/Preview/ExportDropdown.jsx`**

Replaces the disabled "Export" button in PreviewToolbar. Shows a dropdown with two options:

- **"Push to GitHub"** (primary icon: `cloud_upload`)
  - If user has no github_token (dev-mode login): shows "Connect GitHub first" with link to `/auth/github`
  - If token exists: shows confirmation modal with repo name input (default: project slug), "Push" button
  - During push: shows overlay like regenerate
  - Polls `GET /api/projects/{id}/export/status` every 2s
  - On success: shows WhatNextCard

- **"Download ZIP"** (secondary icon: `download`)
  - Triggers direct download via `window.open('/api/projects/{id}/export/zip?token={token}')` or fetch with blob response
  - Shows brief success toast

Dropdown: `bg-surface-container-high rounded-xl border border-outline-variant/10 shadow-xl p-2` positioned below the button.

### WhatNextCard

**`resources/js/pages/Preview/WhatNextCard.jsx`**

Shown after successful GitHub export. Props: `repoUrl`, `repoName`, `projectSlug`.

Card with terminal-style content:
- Title: "Your scaffold is ready!" with icon `check_circle`
- Repo URL as clickable link
- Terminal card (`bg-surface-container-lowest font-mono text-xs`) with instructions:
  ```
  git clone {repoUrl}
  cd {repoName}
  docker-compose up -d
  cp .env.example .env
  php artisan migrate --seed
  # Open Claude Code and start building!
  ```
- "Open on GitHub" button (primary, links to repo URL)

### PreviewLayout Changes

- Add `exportStatus` state (null, 'exporting', 'exported')
- Add `exportData` state ({ github_repo_url, github_repo_name })
- When exported: show WhatNextCard overlay or section below the code viewer
- Export dropdown integrated into PreviewToolbar

### React Auth Callback Route

New route in `app.jsx`: `/auth/callback` renders a small component that:
1. Reads `#token=xxx` from `window.location.hash`
2. Stores token in localStorage
3. Navigates to `/templates`

---

## 5. Testing

### Feature/GitHubAuthTest.php
- OAuth redirect returns 302 to github.com
- Callback creates new user from GitHub profile (mock Socialite)
- Callback logs in existing user and updates token
- Callback returns redirect with Sanctum token in fragment

### Feature/ExportTest.php
- GitHub export returns 202, dispatches PushToGitHubJob (Queue::fake)
- GitHub export rejects when status is not generated (422)
- GitHub export rejects when user has no github_token (422)
- Export status returns correct state for exported project
- Export status returns pending for non-exported project
- ZIP download returns correct content-type and headers
- ZIP download rejects when not generated
- Ownership validation on all export endpoints

### Feature/GitHubServiceTest.php
- Creates repo via API (Http::fake, verify request body)
- Pushes files via Git Data API (Http::fake, verify blob+tree+commit+ref sequence)
- Handles 401 (expired token) with descriptive error
- Handles 422 (repo name taken) with descriptive error

### Mock strategy
- Socialite: `Socialite::shouldReceive('driver->user')` returning mock GitHub user
- GitHub API: `Http::fake()` with sequenced responses for repo creation, blob, tree, commit, ref

---

## 6. File Structure

### New files
```
app/Http/Controllers/Auth/GitHubAuthController.php
app/Http/Controllers/Export/GitHubExportController.php
app/Http/Controllers/Export/ZipExportController.php
app/Services/GitHubService.php
app/Jobs/PushToGitHubJob.php
resources/js/pages/Preview/ExportDropdown.jsx
resources/js/pages/Preview/WhatNextCard.jsx
resources/js/pages/AuthCallback.jsx
tests/Feature/GitHubAuthTest.php
tests/Feature/ExportTest.php
tests/Feature/GitHubServiceTest.php
```

### Files to modify
```
.claude-reference/architecture.md                 — update Export endpoint auth from "Paid" to "Yes" (Stripe gate deferred)
routes/web.php                                    — add GitHub OAuth routes, update SPA catch-all regex to exclude /auth
routes/api.php                                    — add export endpoints
config/services.php                               — add GitHub OAuth config
.env.example                                      — add GITHUB_CLIENT_ID, SECRET, REDIRECT
resources/js/app.jsx                              — add /auth/callback route
resources/js/api.js                               — update ensureAuth for production
resources/js/pages/Preview/PreviewToolbar.jsx     — replace Export button with ExportDropdown
resources/js/pages/Preview/PreviewLayout.jsx      — add export state + WhatNextCard
```

---

## 7. Out of Scope

- Stripe payment gate (skipped per user request — can wrap export endpoints later)
- Re-export to existing repo (always creates new repo)
- Branch protection or repo settings configuration
- Custom domain for exported repos
- GitHub App installation (uses personal access token via OAuth)
- Export history / audit log
- Skeleton files merging (Phase 3 originally planned this, defer to when BYOS deploy needs it)
