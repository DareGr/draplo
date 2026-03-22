<?php

use App\Services\Deploy\HetznerService;
use Illuminate\Support\Facades\Http;

it('creates a server via Hetzner API', function () {
    Http::fake([
        'https://api.hetzner.cloud/v1/servers' => Http::response([
            'server' => [
                'id' => 12345678,
                'name' => 'my-server',
                'status' => 'initializing',
                'public_net' => [
                    'ipv4' => ['ip' => '1.2.3.4'],
                ],
                'server_type' => ['name' => 'cx22'],
            ],
        ], 201),
    ]);

    $service = new HetznerService();
    $result = $service->createServer('fake-api-key', 'my-server', 'cx22');

    expect($result)
        ->toBeArray()
        ->toHaveKey('id', 12345678)
        ->toHaveKey('name', 'my-server')
        ->toHaveKey('status', 'initializing');

    Http::assertSent(fn ($request) =>
        $request->hasHeader('Authorization', 'Bearer fake-api-key') &&
        $request->url() === 'https://api.hetzner.cloud/v1/servers'
    );
});

it('gets server status via Hetzner API', function () {
    Http::fake([
        'https://api.hetzner.cloud/v1/servers/12345678' => Http::response([
            'server' => [
                'id' => 12345678,
                'name' => 'my-server',
                'status' => 'running',
                'public_net' => [
                    'ipv4' => ['ip' => '1.2.3.4'],
                ],
            ],
        ], 200),
    ]);

    $service = new HetznerService();
    $result = $service->getServer('fake-api-key', '12345678');

    expect($result)
        ->toBeArray()
        ->toHaveKey('status', 'running');
});

it('throws on invalid API key', function () {
    Http::fake([
        'https://api.hetzner.cloud/v1/servers' => Http::response([
            'error' => [
                'message' => 'unauthorized',
                'code' => 'unauthorized',
            ],
        ], 401),
    ]);

    $service = new HetznerService();
    $service->createServer('invalid-key', 'my-server');
})->throws(RuntimeException::class);
