<?php

namespace App\Jobs;

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Services\GenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(
        public Project $project
    ) {}

    public function handle(GenerationService $generationService): void
    {
        $this->project->update(['status' => ProjectStatusEnum::Generating]);

        try {
            $generationService->generate($this->project);
        } catch (\Throwable $e) {
            $this->project->update(['status' => ProjectStatusEnum::Failed]);
            Log::error('Generation failed', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
