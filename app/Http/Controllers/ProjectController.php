<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = auth()->user()->projects()
            ->select('id', 'name', 'slug', 'template_slug', 'status', 'updated_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($projects);
    }

    public function destroy(Project $project): Response
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $project->delete();

        return response()->noContent();
    }
}
