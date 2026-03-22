<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
    config(['services.anthropic.api_key' => 'test-key']);
    config(['services.gemini.api_key' => 'test-key']);
    $this->user = User::factory()->create();
});

it('returns model suggestions from AI', function () {
    $suggestedModels = json_encode([
        ['name' => 'Booking', 'description' => 'A booking record', 'fields' => [['name' => 'date', 'type' => 'date']]],
        ['name' => 'Service', 'description' => 'A service offered', 'fields' => [['name' => 'name', 'type' => 'string']]],
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $suggestedModels]],
            'usage' => ['input_tokens' => 500, 'output_tokens' => 300, 'cache_read_input_tokens' => 0],
        ]),
    ]);

    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson("/api/wizard/projects/{$project->id}/suggest", [
            'description' => 'A dental booking platform for clinics',
        ])
        ->assertOk()
        ->assertJsonStructure(['models'])
        ->assertJsonCount(2, 'models');

    Http::assertSentCount(1);
});

it('requires description field', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson("/api/wizard/projects/{$project->id}/suggest", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('description');
});
