<?php

use App\Models\User;
use App\Models\Project;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'TemplateSeeder']);
    $this->user = User::factory()->create();
});

it('creates a project from a template', function () {
    $this->actingAs($this->user)
        ->postJson('/api/wizard/projects', ['template_slug' => 'booking-platform'])
        ->assertCreated()
        ->assertJsonPath('template_slug', 'booking-platform')
        ->assertJsonPath('status', 'draft');
});

it('creates a project without a template', function () {
    $this->actingAs($this->user)
        ->postJson('/api/wizard/projects', [])
        ->assertCreated()
        ->assertJsonPath('template_slug', null)
        ->assertJsonPath('status', 'draft');
});

it('creates project with empty defaults when no template', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/wizard/projects', []);

    $wizardData = $response->json('wizard_data');
    expect($wizardData)->toHaveKey('step_describe');
    expect($wizardData)->toHaveKey('step_users');
    expect($wizardData)->toHaveKey('step_models');
    expect($wizardData)->toHaveKey('step_auth');
    expect($wizardData)->toHaveKey('step_integrations');
});

it('creates project with template defaults when template provided', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/wizard/projects', ['template_slug' => 'booking-platform']);

    $wizardData = $response->json('wizard_data');
    expect($wizardData['step_models']['models'])->toHaveCount(8);
    expect($wizardData['step_users']['roles'])->toHaveCount(3);
});

it('loads a project with wizard data', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->getJson("/api/wizard/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('id', $project->id);
});

it('prevents accessing another user\'s project', function () {
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($this->user)
        ->getJson("/api/wizard/projects/{$project->id}")
        ->assertForbidden();
});

it('saves wizard step data', function () {
    $project = $this->actingAs($this->user)
        ->postJson('/api/wizard/projects', ['template_slug' => 'booking-platform'])
        ->json();

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project['id']}", [
            'step' => 'describe',
            'data' => ['name' => 'DentBook', 'description' => 'Dental booking', 'problem' => 'Double bookings'],
        ])
        ->assertOk()
        ->assertJsonPath('name', 'DentBook')
        ->assertJsonPath('wizard_data.step_describe.name', 'DentBook');
});

it('validates describe step requires name', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project->id}", [
            'step' => 'describe',
            'data' => ['description' => 'No name provided'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('data.name');
});

it('validates models step requires at least one model', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project->id}", [
            'step' => 'models',
            'data' => ['models' => []],
        ])
        ->assertUnprocessable();
});

it('sets status to wizard_done on review step', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project->id}", [
            'step' => 'review',
            'data' => [],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'wizard_done');
});

it('lists user projects', function () {
    Project::factory()->count(3)->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->getJson('/api/projects')
        ->assertOk()
        ->assertJsonCount(3);
});

it('deletes a project', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});

it('prevents deleting another user\'s project', function () {
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertForbidden();
});

it('rejects invalid step names', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project->id}", [
            'step' => 'invalid_step',
            'data' => [],
        ])
        ->assertUnprocessable();
});

it('prevents updating another user\'s project', function () {
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($this->user)
        ->putJson("/api/wizard/projects/{$project->id}", [
            'step' => 'describe',
            'data' => ['name' => 'Hacked'],
        ])
        ->assertForbidden();
});
