<?php

namespace App\Services\Deploy;

use App\Models\ServerConnection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoolifyService
{
    public function healthcheck(string $url, string $apiKey): bool
    {
        try {
            $response = $this->request($url, $apiKey, 'get', '/healthcheck');

            return $response === 'OK' || (is_array($response) && ($response['status'] ?? null) === 'OK');
        } catch (\Throwable) {
            return false;
        }
    }

    public function createApplication(ServerConnection $conn, string $repoUrl): array
    {
        return $this->request($conn->coolify_url, $conn->coolify_api_key, 'post', '/api/v1/applications', [
            'project_uuid' => 'default',
            'server_uuid' => $conn->server_id,
            'environment_name' => 'production',
            'git_repository' => $repoUrl,
            'git_branch' => 'main',
            'build_pack' => 'dockerfile',
            'ports_exposes' => '80',
        ]);
    }

    public function createDatabase(ServerConnection $conn, string $name): array
    {
        return $this->request($conn->coolify_url, $conn->coolify_api_key, 'post', '/api/v1/databases/postgresql', [
            'project_uuid' => 'default',
            'server_uuid' => $conn->server_id,
            'environment_name' => 'production',
            'name' => $name,
            'postgres_user' => 'app',
            'postgres_password' => bin2hex(random_bytes(16)),
            'postgres_db' => $name,
        ]);
    }

    public function setEnvironmentVariables(ServerConnection $conn, string $appUuid, array $vars): void
    {
        foreach ($vars as $key => $value) {
            $this->request($conn->coolify_url, $conn->coolify_api_key, 'post', "/api/v1/applications/{$appUuid}/envs", [
                'key' => $key,
                'value' => $value,
                'is_build_time' => false,
            ]);
        }
    }

    public function deploy(ServerConnection $conn, string $appUuid): array
    {
        return $this->request($conn->coolify_url, $conn->coolify_api_key, 'post', "/api/v1/applications/{$appUuid}/deploy");
    }

    public function getDeployStatus(ServerConnection $conn, string $appUuid): array
    {
        return $this->request($conn->coolify_url, $conn->coolify_api_key, 'get', "/api/v1/applications/{$appUuid}/status");
    }

    public function deleteApplication(ServerConnection $conn, string $appUuid): void
    {
        $this->request($conn->coolify_url, $conn->coolify_api_key, 'delete', "/api/v1/applications/{$appUuid}");
    }

    private function request(string $url, string $apiKey, string $method, string $path, array $data = []): mixed
    {
        $method = strtolower($method);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->{$method}(rtrim($url, '/') . $path, $data);

            $response->throw();

            $body = $response->body();
            $json = json_decode($body, true);

            return $json ?? $body;
        } catch (RequestException $e) {
            Log::error('Coolify API error', [
                'url' => $url,
                'method' => $method,
                'path' => $path,
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);

            throw new \RuntimeException(
                'Coolify API request failed: ' . ($e->response?->json('message') ?? $e->getMessage())
            );
        }
    }
}
