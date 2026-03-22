<?php

namespace App\Services\Deploy;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HetznerService
{
    private const BASE_URL = 'https://api.hetzner.cloud/v1';

    public function createServer(string $apiKey, string $name, string $type = 'cx22'): array
    {
        $cloudInit = <<<'YAML'
#cloud-config
apt:
  sources:
    docker.list:
      source: "deb https://download.docker.com/linux/ubuntu $RELEASE stable"
      keyid: 9DC858229FC7DD38854AE2D88D81803C0EBFCD88
packages:
  - docker-ce
  - docker-ce-cli
  - docker-compose-plugin
runcmd:
  - curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
YAML;

        $response = $this->request($apiKey, 'post', '/servers', [
            'name' => $name,
            'server_type' => $type,
            'image' => 'ubuntu-24.04',
            'location' => 'fsn1',
            'user_data' => $cloudInit,
        ]);

        return $response['server'];
    }

    public function getServer(string $apiKey, string $serverId): array
    {
        $response = $this->request($apiKey, 'get', "/servers/{$serverId}");

        return $response['server'];
    }

    public function deleteServer(string $apiKey, string $serverId): void
    {
        $this->request($apiKey, 'delete', "/servers/{$serverId}");
    }

    private function request(string $apiKey, string $method, string $path, array $data = []): array
    {
        $method = strtolower($method);

        try {
            $response = Http::withToken($apiKey)
                ->retry(3, 1000, fn ($exception) => $exception->response?->status() === 429)
                ->timeout(30)
                ->{$method}(self::BASE_URL . $path, $data);

            if ($response->status() === 401) {
                throw new \RuntimeException('Invalid Hetzner API key.');
            }

            $response->throw();

            return $response->json() ?? [];
        } catch (RequestException $e) {
            Log::error('Hetzner API error', [
                'method' => $method,
                'path' => $path,
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);

            throw new \RuntimeException(
                'Hetzner API request failed: ' . ($e->response?->json('error.message') ?? $e->getMessage())
            );
        }
    }
}
