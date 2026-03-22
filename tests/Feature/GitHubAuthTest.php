<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('redirects to GitHub for OAuth', function () {
    $this->get('/auth/github')
        ->assertRedirect();
});

it('creates new user from GitHub callback', function () {
    $githubUser = new SocialiteUser();
    $githubUser->id = '99999';
    $githubUser->name = 'Test User';
    $githubUser->email = 'test@github.com';
    $githubUser->nickname = 'testuser';
    $githubUser->avatar = 'https://github.com/avatar.png';
    $githubUser->token = 'gh_test_token';

    Socialite::shouldReceive('driver->user')->andReturn($githubUser);

    $this->get('/auth/github/callback')
        ->assertRedirect()
        ->assertRedirectContains('/auth/callback#token=');

    $this->assertDatabaseHas('users', [
        'github_id' => '99999',
        'github_username' => 'testuser',
        'email' => 'test@github.com',
    ]);
});

it('logs in existing user and updates token', function () {
    $user = User::factory()->create([
        'github_id' => '88888',
        'github_token' => 'old_token',
    ]);

    $githubUser = new SocialiteUser();
    $githubUser->id = '88888';
    $githubUser->name = $user->name;
    $githubUser->email = $user->email;
    $githubUser->nickname = 'updated_user';
    $githubUser->avatar = 'https://new-avatar.png';
    $githubUser->token = 'new_token';

    Socialite::shouldReceive('driver->user')->andReturn($githubUser);

    $this->get('/auth/github/callback')
        ->assertRedirect();

    $user->refresh();
    expect($user->github_username)->toBe('updated_user');
    expect(User::where('github_id', '88888')->count())->toBe(1);
});
