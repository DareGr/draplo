<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatusEnum;
use App\Jobs\GenerateProjectJob;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerationController extends Controller
{
    public function generate(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status !== ProjectStatusEnum::WizardDone) {
            return response()->json(['message' => 'Project must have completed wizard before generating. Use regenerate for already-generated projects.'], 422);
        }

        GenerateProjectJob::dispatch($project);

        return response()->json([
            'status' => 'generating',
            'message' => 'Generation started.',
        ], 202);
    }

    public function status(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $data = ['status' => $project->status->value];

        if ($project->status === ProjectStatusEnum::Generated) {
            $generation = $project->generations()->latest('created_at')->first();
            $files = collect($project->generation_output ?? [])
                ->map(fn($f) => ['path' => $f['path'], 'size' => strlen($f['content'] ?? '')])
                ->toArray();

            $data['files'] = $files;
            if ($generation) {
                $data['generation'] = [
                    'prompt_tokens' => $generation->prompt_tokens,
                    'completion_tokens' => $generation->completion_tokens,
                    'cost_usd' => $generation->cost_usd,
                    'model' => $generation->model,
                    'provider' => $generation->provider,
                    'duration_ms' => $generation->duration_ms,
                    'cached' => $generation->cached,
                    'cache_read_tokens' => $generation->cache_read_tokens,
                    'created_at' => $generation->created_at?->toISOString(),
                ];
            }
        }

        return response()->json($data);
    }

    public function regenerate(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $project->update([
            'input_hash' => null,
            'generation_output' => null,
        ]);

        GenerateProjectJob::dispatch($project);

        return response()->json([
            'status' => 'generating',
            'message' => 'Regeneration started.',
        ], 202);
    }

    public function preview(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status !== ProjectStatusEnum::Generated) {
            return response()->json(['message' => 'No generated output available.'], 404);
        }

        return response()->json(['files' => $project->generation_output ?? []]);
    }

    public function previewFile(Project $project, string $filepath): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status !== ProjectStatusEnum::Generated) {
            return response()->json(['message' => 'No generated output available.'], 404);
        }

        $file = collect($project->generation_output ?? [])
            ->firstWhere('path', $filepath);

        if (!$file) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return response()->json($file);
    }

    public function updatePreviewFile(Request $request, Project $project, string $filepath): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status !== ProjectStatusEnum::Generated) {
            return response()->json(['message' => 'Project is not generated.'], 404);
        }

        $request->validate(['content' => 'required|string']);

        $files = $project->generation_output;
        $index = collect($files)->search(fn($f) => $f['path'] === $filepath);

        if ($index === false) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $files[$index]['content'] = $request->input('content');
        $project->update(['generation_output' => $files]);

        return response()->json($files[$index]);
    }
}
