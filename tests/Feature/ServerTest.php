<?php

use App\Jobs\ProvisionServerJob;
use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create(['github_token' => 'test_token']);
});

it('dispatches ProvisionServerJob when creating hetzner server', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson('/api/servers', [
            'provider' => 'hetzner',
            'server_name' => 'my-server',
            'api_key' => 'hetzner-api-key-123',
            'server_spec' => 'cx22',
        ])
        ->assertStatus(202)
        ->assertJsonPath('provider', 'hetzner')
        ->assertJsonPath('status', 'pending');

    Queue::assertPushed(ProvisionServerJob::class);
});

it('creates manual server when Coolify is healthy', function () {
    Http::fake([
        'https://coolify.example.com/*' => Http::response('OK', 200),
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/servers', [
            'provider' => 'manual',
            'server_name' => 'manual-server',
            'coolify_url' => 'https://coolify.example.com',
            'coolify_api_key' => 'coolify-key-123',
            'server_ip' => '10.0.0.1',
        ])
        ->assertStatus(201)
        ->assertJsonPath('provider', 'manual')
        ->assertJsonPath('status', 'active');
});

it('rejects manual server when Coolify is unhealthy', function () {
    Http::fake([
        'https://coolify.example.com/*' => Http::response('Error', 500),
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/servers', [
            'provider' => 'manual',
            'server_name' => 'manual-server',
            'coolify_url' => 'https://coolify.example.com',
            'coolify_api_key' => 'coolify-key-123',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Could not connect to Coolify. Please verify the URL and API key.');
});

it('lists only the authenticated users servers', function () {
    ServerConnection::factory()->count(2)->create(['user_id' => $this->user->id]);
    ServerConnection::factory()->count(3)->create(); // other user's servers

    $this->actingAs($this->user)
        ->getJson('/api/servers')
        ->assertOk()
        ->assertJsonCount(2);
});

it('deletes a server connection and returns 204', function () {
    $server = ServerConnection::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/servers/{$server->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('server_connections', ['id' => $server->id]);
});

it('pings Coolify endpoint for health check', function () {
    Http::fake([
        '*' => Http::response('OK', 200),
    ]);

    $server = ServerConnection::factory()->create([
        'user_id' => $this->user->id,
        'coolify_url' => 'https://coolify.example.com',
        'coolify_api_key' => 'key-123',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/servers/{$server->id}/health")
        ->assertOk()
        ->assertJsonPath('healthy', true);
});

it('prevents accessing another users server', function () {
    $other = User::factory()->create();
    $server = ServerConnection::factory()->create(['user_id' => $other->id]);

    $this->actingAs($this->user)
        ->getJson("/api/servers/{$server->id}")
        ->assertForbidden();
});
