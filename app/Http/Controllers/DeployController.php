<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatusEnum;
use App\Jobs\DeployToCoolifyJob;
use App\Models\Project;
use App\Services\Deploy\CoolifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeployController extends Controller
{
    public function deploy(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->status !== ProjectStatusEnum::Exported) {
            return response()->json([
                'message' => 'Project must be exported before deploying.',
            ], 422);
        }

        if (! $project->github_repo_url) {
            return response()->json([
                'message' => 'Project must have a GitHub repository URL.',
            ], 422);
        }

        $request->validate([
            'server_id' => 'required|exists:server_connections,id',
        ]);

        $server = auth()->user()->serverConnections()->findOrFail($request->input('server_id'));

        if (! $server->isActive()) {
            return response()->json([
                'message' => 'Server connection is not active.',
            ], 422);
        }

        DeployToCoolifyJob::dispatch($project, $server);

        return response()->json([
            'message' => 'Deploy started.',
            'project_id' => $project->id,
        ], 202);
    }

    public function status(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $status = match ($project->status) {
            ProjectStatusEnum::Deploying => 'deploying',
            ProjectStatusEnum::Deployed => 'deployed',
            default => 'pending',
        };

        return response()->json([
            'status' => $status,
            'deploy_url' => $project->deploy_url,
            'deployed_at' => $project->deployed_at,
        ]);
    }

    public function teardown(Project $project, CoolifyService $coolifyService): Response
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if ($project->coolify_app_id) {
            $server = auth()->user()->serverConnections()->first();

            if ($server) {
                try {
                    $coolifyService->deleteApplication($server, $project->coolify_app_id);
                } catch (\Throwable) {
                    // Ignore errors — the app may already be deleted
                }
            }
        }

        $project->update([
            'status' => ProjectStatusEnum::Exported,
            'coolify_app_id' => null,
            'coolify_db_id' => null,
            'deploy_url' => null,
            'deployed_at' => null,
        ]);

        return response()->noContent();
    }
}
