<?php

it('returns feature flags', function () {
    $this->getJson('/api/config/flags')
        ->assertOk()
        ->assertJsonStructure([
            'coolify_enabled',
            'github_enabled',
            'templates_enabled',
            'threejs_hero_enabled',
            'byos_hetzner_enabled',
        ]);
});

it('flags are boolean values', function () {
    $response = $this->getJson('/api/config/flags');
    foreach ($response->json() as $value) {
        expect($value)->toBeBool();
    }
});
