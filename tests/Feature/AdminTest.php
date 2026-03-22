<?php

use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SettingsSeeder']);
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
});

it('returns AI settings for admin', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/admin/settings')
        ->assertOk()
        ->assertJsonStructure([
            'ai_provider',
            'ai_model',
            'ai_max_tokens',
            'generation_rate_limit',
        ]);
});

it('returns 403 for non-admin on settings', function () {
    $this->actingAs($this->user)
        ->getJson('/api/admin/settings')
        ->assertForbidden();
});

it('updates provider and model settings', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/admin/settings', [
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-2.5-pro',
        ])
        ->assertOk()
        ->assertJsonPath('ai_provider', 'gemini')
        ->assertJsonPath('ai_model', 'gemini-2.5-pro');
});

it('validates provider must be anthropic or gemini', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/admin/settings', [
            'ai_provider' => 'openai',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ai_provider');
});

it('returns stats with correct structure', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/admin/stats')
        ->assertOk()
        ->assertJsonStructure([
            'users_count',
            'projects_count',
            'generations_count',
            'total_cost_usd',
            'generations_today',
            'active_provider',
            'active_model',
        ]);
});

it('returns 403 for non-admin on stats', function () {
    $this->actingAs($this->user)
        ->getJson('/api/admin/stats')
        ->assertForbidden();
});
