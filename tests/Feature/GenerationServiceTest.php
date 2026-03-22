<?php

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Models\User;
use App\Services\GenerationService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
    config(['services.anthropic.api_key' => 'test-key']);
    config(['services.gemini.api_key' => 'test-key']);
    $this->user = User::factory()->create();
});

it('generates and stores output with valid XML', function () {
    $sampleOutput = implode("\n", [
        '<file path="CLAUDE.md"># Project Context</file>',
        '<file path="PROJECT.md"># Project Overview</file>',
        '<file path="todo.md">- [ ] Task 1</file>',
        '<file path=".claude-reference/architecture.md"># Architecture</file>',
        '<file path=".claude-reference/constants.md"># Constants</file>',
        '<file path=".claude-reference/patterns.md"># Patterns</file>',
        '<file path=".claude-reference/decisions.md"># Decisions</file>',
        '<file path="routes/api.php"><?php Route::get(\'/\', fn() => \'ok\');</file>',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $sampleOutput]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 5000, 'cache_read_input_tokens' => 500],
        ]),
    ]);

    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
        'wizard_data' => [
            'step_describe' => ['name' => 'TestApp', 'description' => 'A test app', 'problem' => 'Testing'],
            'step_users' => ['app_type' => 'b2b', 'roles' => [['name' => 'admin', 'description' => 'Administrator']]],
            'step_models' => ['models' => [['name' => 'Task', 'description' => 'A task', 'fields' => [['name' => 'title', 'type' => 'string']]]]],
            'step_auth' => ['multi_tenant' => false, 'auth_method' => 'sanctum', 'guest_access' => false],
            'step_integrations' => ['selected' => ['stripe'], 'notes' => ''],
        ],
    ]);

    $service = app(GenerationService::class);
    $service->generate($project);

    $project->refresh();
    expect($project->status)->toBe(ProjectStatusEnum::Generated);
    expect($project->generation_output)->toHaveCount(8);
    expect($project->input_hash)->not->toBeNull();

    // Check a generation record was created
    expect($project->generations()->count())->toBe(1);
    $gen = $project->generations()->first();
    expect($gen->provider)->toBe('anthropic');
    expect($gen->prompt_tokens)->toBe(1000);
    expect($gen->cached)->toBeFalse();

    Http::assertSentCount(1);
});

it('uses cache when input hash matches', function () {
    Http::fake();

    $wizardData = [
        'step_describe' => ['name' => 'CacheApp', 'description' => 'Cache test', 'problem' => 'Caching'],
        'step_users' => ['app_type' => 'b2c', 'roles' => []],
        'step_models' => ['models' => []],
        'step_auth' => ['multi_tenant' => false, 'auth_method' => 'sanctum', 'guest_access' => false],
        'step_integrations' => ['selected' => [], 'notes' => ''],
    ];

    $inputHash = hash('sha256', json_encode($wizardData));

    // Create a project with existing generation output and matching hash
    $existingProject = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::Generated,
        'wizard_data' => $wizardData,
        'input_hash' => $inputHash,
        'generation_output' => [
            ['path' => 'CLAUDE.md', 'content' => '# Cached'],
        ],
    ]);

    // Create a new project with same wizard data
    $newProject = Project::factory()->create([
        'user_id' => $this->user->id,
        'status' => ProjectStatusEnum::WizardDone,
        'wizard_data' => $wizardData,
    ]);

    $service = app(GenerationService::class);
    $service->generate($newProject);

    $newProject->refresh();
    expect($newProject->status)->toBe(ProjectStatusEnum::Generated);
    expect($newProject->generation_output)->toBe([['path' => 'CLAUDE.md', 'content' => '# Cached']]);
    expect($newProject->input_hash)->toBe($inputHash);

    // Check generation record was created with cached flag
    $gen = $newProject->generations()->first();
    expect($gen->cached)->toBeTrue();
    expect($gen->provider)->toBe('cache');

    // No HTTP calls should have been made
    Http::assertSentCount(0);
});

it('builds user message correctly from wizard data', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'wizard_data' => [
            'step_describe' => ['name' => 'BookingApp', 'description' => 'A booking platform', 'problem' => 'Double bookings'],
            'step_users' => ['app_type' => 'b2b', 'roles' => [['name' => 'admin', 'description' => 'Manages everything']]],
            'step_models' => ['models' => [['name' => 'Booking', 'description' => 'A booking', 'locked' => true, 'fields' => [['name' => 'date', 'type' => 'date']]]]],
            'step_auth' => ['multi_tenant' => true, 'auth_method' => 'sanctum', 'guest_access' => true, 'guest_description' => 'Can view availability'],
            'step_integrations' => ['selected' => ['stripe', 'email'], 'notes' => 'Need webhook support'],
        ],
    ]);

    $service = app(GenerationService::class);
    $message = $service->buildUserMessage($project);

    expect($message)->toContain('BookingApp');
    expect($message)->toContain('A booking platform');
    expect($message)->toContain('Double bookings');
    expect($message)->toContain('b2b');
    expect($message)->toContain('admin: Manages everything');
    expect($message)->toContain('Booking');
    expect($message)->toContain('(locked - required)');
    expect($message)->toContain('date (date)');
    expect($message)->toContain('Multi-tenant: Yes');
    expect($message)->toContain('Guest access: Yes - Can view availability');
    expect($message)->toContain('stripe, email');
    expect($message)->toContain('Need webhook support');
});
