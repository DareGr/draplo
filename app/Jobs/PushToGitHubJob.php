<?php

namespace App\Jobs;

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushToGitHubJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(
        public Project $project,
        public User $user,
        public string $repoName,
    ) {}

    public function handle(GitHubService $githubService): void
    {
        try {
            $result = $githubService->export($this->user, $this->project, $this->repoName);

            $this->project->update([
                'github_repo_url' => $result['repo_url'],
                'github_repo_name' => $result['repo_name'],
                'status' => ProjectStatusEnum::Exported,
                'exported_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->project->update(['status' => ProjectStatusEnum::Generated]);
            Log::error('GitHub export failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
