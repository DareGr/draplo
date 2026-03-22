<?php

use App\Enums\ProjectStatusEnum;
use App\Jobs\DeployToCoolifyJob;
use App\Models\Project;
use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create(['github_token' => 'test_token']);
    $this->server = ServerConnection::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);
});

it('dispatches DeployToCoolifyJob for exported project', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Exported,
        'github_repo_url' => 'https://github.com/user/repo',
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/deploy", [
            'server_id' => $this->server->id,
        ])
        ->assertStatus(202)
        ->assertJsonPath('message', 'Deploy started.');

    Queue::assertPushed(DeployToCoolifyJob::class);
});

it('rejects deploy for non-exported project', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'github_repo_url' => 'https://github.com/user/repo',
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/deploy", [
            'server_id' => $this->server->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Project must be exported before deploying.');

    Queue::assertNothingPushed();
});

it('rejects deploy for inactive server', function () {
    Queue::fake();

    $inactiveServer = ServerConnection::factory()->provisioning()->create([
        'user_id' => $this->user->id,
    ]);

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Exported,
        'github_repo_url' => 'https://github.com/user/repo',
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/deploy", [
            'server_id' => $inactiveServer->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Server connection is not active.');

    Queue::assertNothingPushed();
});

it('returns deployed status with URL', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Deployed,
        'deploy_url' => 'https://myapp.example.com',
        'deployed_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/deploy/status")
        ->assertOk()
        ->assertJsonPath('status', 'deployed')
        ->assertJsonPath('deploy_url', 'https://myapp.example.com');
});

it('tears down a deployed project', function () {
    Http::fake([
        '*' => Http::response(null, 204),
    ]);

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Deployed,
        'coolify_app_id' => 'app-uuid-123',
        'coolify_db_id' => 'db-uuid-456',
        'deploy_url' => 'https://myapp.example.com',
        'deployed_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/projects/{$project->id}/deploy")
        ->assertNoContent();

    $project->refresh();
    expect($project->status)->toBe(ProjectStatusEnum::Exported);
    expect($project->coolify_app_id)->toBeNull();
    expect($project->deploy_url)->toBeNull();
});

it('prevents deploying another users project', function () {
    Queue::fake();

    $other = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $other->id,
        'status' => ProjectStatusEnum::Exported,
        'github_repo_url' => 'https://github.com/other/repo',
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/deploy", [
            'server_id' => $this->server->id,
        ])
        ->assertForbidden();

    Queue::assertNothingPushed();
});
