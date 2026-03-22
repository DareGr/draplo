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

    Http::assertSent(fn ($r) => str_contains($r->url(), '/user/repos'));
    Http::assertSent(fn ($r) => str_contains($r->url(), '/git/blobs'));
    Http::assertSent(fn ($r) => str_contains($r->url(), '/git/trees'));
    Http::assertSent(fn ($r) => str_contains($r->url(), '/git/commits'));
    Http::assertSent(fn ($r) => str_contains($r->url(), '/git/refs'));
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
            'message' => 'name already exists on this account',
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
