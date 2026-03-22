<?php

namespace App\Jobs;

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Models\ServerConnection;
use App\Services\Deploy\CoolifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeployToCoolifyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public Project $project,
        public ServerConnection $serverConnection,
    ) {}

    public function handle(CoolifyService $coolifyService): void
    {
        try {
            $this->project->update(['status' => ProjectStatusEnum::Deploying]);

            // Create application
            $app = $coolifyService->createApplication(
                $this->serverConnection,
                $this->project->github_repo_url,
            );

            $appUuid = $app['uuid'];
            $this->project->update(['coolify_app_id' => $appUuid]);

            // Create database
            $dbName = str_replace('-', '_', $this->project->slug) . '_db';
            $db = $coolifyService->createDatabase($this->serverConnection, $dbName);
            $this->project->update(['coolify_db_id' => $db['uuid'] ?? $db['id'] ?? null]);

            // Set environment variables
            $coolifyService->setEnvironmentVariables($this->serverConnection, $appUuid, [
                'APP_NAME' => $this->project->name,
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => 'localhost',
                'DB_DATABASE' => $dbName,
            ]);

            // Deploy
            $coolifyService->deploy($this->serverConnection, $appUuid);

            // Poll until deployed (max ~4 minutes)
            $maxAttempts = 48;
            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep(5);

                $status = $coolifyService->getDeployStatus($this->serverConnection, $appUuid);
                $appStatus = $status['status'] ?? null;

                if ($appStatus === 'running') {
                    $deployUrl = $status['fqdn'] ?? ('http://' . $this->serverConnection->server_ip);

                    $this->project->update([
                        'status' => ProjectStatusEnum::Deployed,
                        'deploy_url' => $deployUrl,
                        'deployed_at' => now(),
                    ]);

                    return;
                }

                if ($appStatus === 'exited' || $appStatus === 'error') {
                    break;
                }
            }

            $this->project->update(['status' => ProjectStatusEnum::Failed]);
            Log::warning('Deploy timed out or failed', [
                'project_id' => $this->project->id,
            ]);
        } catch (\Throwable $e) {
            $this->project->update(['status' => ProjectStatusEnum::Failed]);
            Log::error('Deploy to Coolify failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
