<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private SettingsService $settings
    ) {}

    public function settings(): JsonResponse
    {
        return response()->json([
            'ai_provider' => $this->settings->get('ai_provider', config('services.ai.provider')),
            'ai_model' => $this->settings->get('ai_model', config('services.ai.model')),
            'ai_max_tokens' => (int) $this->settings->get('ai_max_tokens', config('services.ai.max_tokens')),
            'generation_rate_limit' => (int) $this->settings->get('generation_rate_limit', 5),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_provider' => ['sometimes', 'string', 'in:anthropic,gemini'],
            'ai_model' => ['sometimes', 'string', 'max:100'],
            'ai_max_tokens' => ['sometimes', 'integer', 'min:1000', 'max:100000'],
            'generation_rate_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        foreach ($validated as $key => $value) {
            $this->settings->set($key, $value);
        }

        return $this->settings();
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'users_count' => User::count(),
            'projects_count' => Project::count(),
            'generations_count' => Generation::count(),
            'total_cost_usd' => (float) Generation::sum('cost_usd'),
            'generations_today' => Generation::whereDate('created_at', today())->count(),
            'active_provider' => $this->settings->get('ai_provider', config('services.ai.provider')),
            'active_model' => $this->settings->get('ai_model', config('services.ai.model')),
        ]);
    }
}
