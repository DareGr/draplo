<?php

use App\Models\ServerConnection;
use App\Services\Deploy\CoolifyService;
use Illuminate\Support\Facades\Http;

it('returns true for healthy Coolify instance', function () {
    Http::fake([
        'https://coolify.example.com/healthcheck' => Http::response('OK', 200),
    ]);

    $service = new CoolifyService();
    $result = $service->healthcheck('https://coolify.example.com', 'api-key');

    expect($result)->toBeTrue();
});

it('returns false for unreachable Coolify instance', function () {
    Http::fake([
        'https://coolify.example.com/healthcheck' => Http::response('Error', 500),
    ]);

    $service = new CoolifyService();
    $result = $service->healthcheck('https://coolify.example.com', 'api-key');

    expect($result)->toBeFalse();
});

it('creates an application via Coolify API', function () {
    Http::fake([
        'https://coolify.example.com/api/v1/applications' => Http::response([
            'uuid' => 'app-uuid-123',
            'name' => 'my-app',
        ], 201),
    ]);

    $server = ServerConnection::factory()->create([
        'coolify_url' => 'https://coolify.example.com',
        'coolify_api_key' => 'api-key',
        'server_id' => 'srv-123',
    ]);

    $service = new CoolifyService();
    $result = $service->createApplication($server, 'https://github.com/user/repo');

    expect($result)
        ->toBeArray()
        ->toHaveKey('uuid', 'app-uuid-123');

    Http::assertSent(fn ($request) =>
        $request->url() === 'https://coolify.example.com/api/v1/applications' &&
        $request['git_repository'] === 'https://github.com/user/repo'
    );
});

it('creates a database via Coolify API', function () {
    Http::fake([
        'https://coolify.example.com/api/v1/databases/postgresql' => Http::response([
            'uuid' => 'db-uuid-456',
            'name' => 'my_app_db',
        ], 201),
    ]);

    $server = ServerConnection::factory()->create([
        'coolify_url' => 'https://coolify.example.com',
        'coolify_api_key' => 'api-key',
        'server_id' => 'srv-123',
    ]);

    $service = new CoolifyService();
    $result = $service->createDatabase($server, 'my_app_db');

    expect($result)
        ->toBeArray()
        ->toHaveKey('uuid', 'db-uuid-456');
});

it('triggers deploy via Coolify API', function () {
    Http::fake([
        'https://coolify.example.com/api/v1/applications/app-uuid-123/deploy' => Http::response([
            'message' => 'Deployment started.',
            'deployment_uuid' => 'deploy-789',
        ], 200),
    ]);

    $server = ServerConnection::factory()->create([
        'coolify_url' => 'https://coolify.example.com',
        'coolify_api_key' => 'api-key',
    ]);

    $service = new CoolifyService();
    $result = $service->deploy($server, 'app-uuid-123');

    expect($result)
        ->toBeArray()
        ->toHaveKey('message', 'Deployment started.');

    Http::assertSent(fn ($request) =>
        $request->url() === 'https://coolify.example.com/api/v1/applications/app-uuid-123/deploy'
    );
});
