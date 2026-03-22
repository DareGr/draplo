<?php

use App\Models\User;

it('returns a Sanctum token via dev login', function () {
    User::factory()->create(['email' => 'dev@draplo.test']);

    $response = $this->get('/dev/login');

    $response->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

it('blocks dev login in production', function () {
    User::factory()->create(['email' => 'dev@draplo.test']);

    app()->detectEnvironment(fn () => 'production');

    $response = $this->get('/dev/login');

    $response->assertForbidden();
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});
