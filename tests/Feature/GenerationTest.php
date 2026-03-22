<?php

use App\Enums\ProjectStatusEnum;
use App\Jobs\GenerateProjectJob;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
    $this->user = User::factory()->create();
});

it('triggers generation and returns 202 for wizard_done project', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/generate")
        ->assertStatus(202)
        ->assertJsonPath('status', 'generating');

    Queue::assertPushed(GenerateProjectJob::class, function ($job) use ($project) {
        return $job->project->id === $project->id;
    });
});

it('rejects generation from draft status', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Draft,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/generate")
        ->assertStatus(422);

    Queue::assertNothingPushed();
});

it('returns generation status for generating project', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generating,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/generation")
        ->assertOk()
        ->assertJsonPath('status', 'generating');
});

it('returns generation status with files when generated', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'PROJECT.md', 'content' => '# Project'],
    ];

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => $files,
    ]);

    Generation::create([
        'project_id' => $project->id,
        'input_hash' => 'abc123',
        'prompt_tokens' => 1000,
        'completion_tokens' => 5000,
        'cache_read_tokens' => 500,
        'cost_usd' => 0.078,
        'model' => 'claude-sonnet-4-6',
        'provider' => 'anthropic',
        'duration_ms' => 12000,
        'cached' => false,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/generation")
        ->assertOk()
        ->assertJsonPath('status', 'generated');

    expect($response->json('files'))->toHaveCount(2);
    expect($response->json('generation.model'))->toBe('claude-sonnet-4-6');
    expect($response->json('generation.provider'))->toBe('anthropic');
});

it('returns files on preview when generated', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'routes/api.php', 'content' => '<?php Route::get("/", fn() => "ok");'],
    ];

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => $files,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/preview")
        ->assertOk()
        ->assertJsonPath('files.0.path', 'CLAUDE.md')
        ->assertJsonCount(2, 'files');
});

it('returns 404 on preview when not generated', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/preview")
        ->assertStatus(404);
});

it('returns a single file by path', function () {
    $files = [
        ['path' => 'CLAUDE.md', 'content' => '# Context'],
        ['path' => 'routes/api.php', 'content' => '<?php Route::get("/", fn() => "ok");'],
    ];

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => $files,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/preview/routes/api.php")
        ->assertOk()
        ->assertJsonPath('path', 'routes/api.php');
});

it('returns 403 for another user project on generate', function () {
    Queue::fake();

    $otherUser = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $otherUser->id,
        'status' => ProjectStatusEnum::WizardDone,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/generate")
        ->assertForbidden();
});

it('returns 403 for another user project on status', function () {
    $otherUser = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/generation")
        ->assertForbidden();
});

it('returns 403 for another user project on preview', function () {
    $otherUser = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $otherUser->id,
        'status' => ProjectStatusEnum::Generated,
        'generation_output' => [['path' => 'test.md', 'content' => 'test']],
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/projects/{$project->id}/preview")
        ->assertForbidden();
});

it('regenerate clears cache and dispatches job', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'input_hash' => 'old-hash',
        'generation_output' => [['path' => 'test.md', 'content' => 'old']],
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/regenerate")
        ->assertStatus(202)
        ->assertJsonPath('status', 'generating');

    $project->refresh();
    expect($project->input_hash)->toBeNull();
    expect($project->generation_output)->toBeNull();

    Queue::assertPushed(GenerateProjectJob::class);
});

it('rate limits after 5 generations in an hour', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
    ]);

    // Create 5 generation records in the last hour
    for ($i = 0; $i < 5; $i++) {
        Generation::create([
            'project_id' => $project->id,
            'input_hash' => "hash-{$i}",
            'prompt_tokens' => 100,
            'completion_tokens' => 200,
            'cache_read_tokens' => 0,
            'cost_usd' => 0.01,
            'model' => 'claude-sonnet-4-6',
            'provider' => 'anthropic',
            'duration_ms' => 1000,
            'cached' => false,
            'created_at' => now()->subMinutes(10),
        ]);
    }

    $this->actingAs($this->user)
        ->postJson("/api/projects/{$project->id}/generate")
        ->assertStatus(429);
});
