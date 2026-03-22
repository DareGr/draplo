<?php

namespace App\Http\Controllers\Export;

use App\Enums\ProjectStatusEnum;
use App\Http\Controllers\Controller;
use App\Jobs\PushToGitHubJob;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubExportController extends Controller
{
    public function export(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if (!in_array($project->status, [ProjectStatusEnum::Generated, ProjectStatusEnum::Exported])) {
            return response()->json(['message' => 'Project must be generated before exporting.'], 422);
        }

        $user = $request->user();
        if (!$user->github_token) {
            return response()->json(['message' => 'GitHub not connected. Please login via GitHub first.'], 422);
        }

        $repoName = $request->input('repo_name', $project->slug);

        $project->update(['status' => ProjectStatusEnum::Exporting]);

        PushToGitHubJob::dispatch($project, $user, $repoName);

        return response()->json([
            'status' => 'exporting',
            'message' => 'Export started.',
        ], 202);
    }

    public function status(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status === ProjectStatusEnum::Exporting) {
            return response()->json(['status' => 'exporting']);
        }

        if ($project->status === ProjectStatusEnum::Exported) {
            return response()->json([
                'status' => 'exported',
                'github_repo_url' => $project->github_repo_url,
                'github_repo_name' => $project->github_repo_name,
                'exported_at' => $project->exported_at?->toISOString(),
            ]);
        }

        return response()->json(['status' => 'pending']);
    }
}
