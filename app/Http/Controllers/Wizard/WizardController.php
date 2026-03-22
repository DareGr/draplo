<?php

namespace App\Http\Controllers\Wizard;

use App\Enums\ProjectStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProjectRequest;
use App\Http\Requests\SaveWizardStepRequest;
use App\Models\Project;
use App\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class WizardController extends Controller
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    private static function emptyDefaults(): array
    {
        return [
            'step_describe' => ['name' => '', 'description' => '', 'problem' => ''],
            'step_users' => ['app_type' => null, 'roles' => []],
            'step_models' => ['models' => []],
            'step_auth' => ['multi_tenant' => false, 'auth_method' => 'sanctum', 'guest_access' => false, 'guest_description' => ''],
            'step_integrations' => ['selected' => [], 'notes' => ''],
        ];
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        $templateSlug = $request->input('template_slug');

        if ($templateSlug) {
            $wizardData = $this->templateService->getDefaults($templateSlug) ?? self::emptyDefaults();
        } else {
            $wizardData = self::emptyDefaults();
        }

        $project = $request->user()->projects()->create([
            'name' => $wizardData['step_describe']['name'] ?: 'Untitled Project',
            'slug' => Str::slug($wizardData['step_describe']['name'] ?: 'project-' . Str::random(6)),
            'template_slug' => $templateSlug,
            'description' => $wizardData['step_describe']['description'] ?? null,
            'wizard_data' => $wizardData,
            'status' => ProjectStatusEnum::Draft,
        ]);

        return response()->json($project, 201);
    }

    public function show(Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        return response()->json($project);
    }

    public function update(SaveWizardStepRequest $request, Project $project): JsonResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $step = $request->input('step');
        $data = $request->input('data');

        $wizardData = $project->wizard_data ?? [];
        $wizardData["step_{$step}"] = $data;
        $project->wizard_data = $wizardData;

        // Update project name/description from describe step
        if ($step === 'describe') {
            $project->name = $data['name'] ?? $project->name;
            $project->slug = Str::slug($data['name'] ?? $project->slug);
            $project->description = $data['description'] ?? $project->description;
        }

        // Mark wizard as complete on review step
        if ($step === 'review') {
            $project->status = ProjectStatusEnum::WizardDone;
        }

        $project->save();

        return response()->json($project);
    }
}
