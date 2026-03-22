<?php

namespace App\Jobs;

use App\Models\ServerConnection;
use App\Services\Deploy\HetznerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public ServerConnection $serverConnection,
    ) {}

    public function handle(HetznerService $hetznerService): void
    {
        try {
            $this->serverConnection->update(['status' => 'provisioning']);

            $server = $hetznerService->createServer(
                $this->serverConnection->encrypted_api_key,
                $this->serverConnection->server_name,
                $this->serverConnection->server_spec ?? 'cx22',
            );

            $this->serverConnection->update([
                'server_id' => (string) $server['id'],
                'server_ip' => $server['public_net']['ipv4']['ip'] ?? null,
            ]);

            // Poll until server is running (max ~5 minutes)
            $maxAttempts = 60;
            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep(5);

                $status = $hetznerService->getServer(
                    $this->serverConnection->encrypted_api_key,
                    (string) $server['id'],
                );

                if ($status['status'] === 'running') {
                    $this->serverConnection->update([
                        'server_ip' => $status['public_net']['ipv4']['ip'] ?? $this->serverConnection->server_ip,
                        'status' => 'installing',
                        'coolify_url' => 'http://' . ($status['public_net']['ipv4']['ip'] ?? $this->serverConnection->server_ip) . ':8000',
                    ]);

                    return;
                }
            }

            $this->serverConnection->update(['status' => 'error']);
            Log::warning('Server provisioning timed out', [
                'server_connection_id' => $this->serverConnection->id,
            ]);
        } catch (\Throwable $e) {
            $this->serverConnection->update(['status' => 'error']);
            Log::error('Server provisioning failed', [
                'server_connection_id' => $this->serverConnection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
