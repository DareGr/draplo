<?php

it('returns a list of templates', function () {
    $this->artisan('db:seed', ['--class' => 'TemplateSeeder']);

    $response = $this->getJson('/api/templates');

    $response->assertOk();

    $templates = $response->json();
    expect($templates)->toBeArray();
    expect(count($templates))->toBe(25);

    $bookingPlatform = collect($templates)->firstWhere('slug', 'booking-platform');
    expect($bookingPlatform)->not->toBeNull();
    expect($bookingPlatform['available'])->toBeTrue();
});

it('returns templates without authentication', function () {
    $this->artisan('db:seed', ['--class' => 'TemplateSeeder']);

    $this->getJson('/api/templates')->assertOk();
});
