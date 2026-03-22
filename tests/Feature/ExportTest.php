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
    if (!class_exists('ZipArchive')) {
        $this->markTestSkipped('ZipArchive extension not available.');
    }

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
