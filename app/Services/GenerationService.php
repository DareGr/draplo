<?php

namespace App\Services;

use App\Enums\ProjectStatusEnum;
use App\Models\Generation;
use App\Models\Project;
use App\Services\AI\AiProviderFactory;
use App\Services\AI\AiResponse;

class GenerationService
{
    public function __construct(
        private AiProviderFactory $providerFactory,
        private OutputParserService $parser,
        private SettingsService $settings,
    ) {}

    public function generate(Project $project): void
    {
        // Step 1: Check cache
        if ($this->tryCache($project)) {
            return;
        }

        // Step 2-3: Build prompts
        $systemPrompt = $this->buildSystemPrompt($project);
        $userMessage = $this->buildUserMessage($project);

        // Step 4: Call AI
        $maxTokens = (int) $this->settings->get('ai_max_tokens', config('services.ai.max_tokens', 16000));
        $provider = $this->providerFactory->resolve();
        $response = $provider->generate($systemPrompt, $userMessage, $maxTokens);

        // Step 5: Parse
        $files = $this->parser->parse($response->content);

        // Step 6: Validate (with retry)
        $errors = $this->parser->validate($files);
        if (!empty($errors)) {
            $retryMessage = $userMessage . "\n\nIMPORTANT: Your previous output was invalid. Issues: " . implode('; ', $errors) . ". Please regenerate ALL required files.";
            $response = $provider->generate($systemPrompt, $retryMessage, $maxTokens);
            $files = $this->parser->parse($response->content);
            $errors = $this->parser->validate($files);

            if (!empty($errors)) {
                throw new \RuntimeException('Generation validation failed after retry: ' . implode('; ', $errors));
            }
        }

        // Step 7: Store + track cost
        $inputHash = hash('sha256', json_encode($project->wizard_data));
        $project->update([
            'generation_output' => $files,
            'input_hash' => $inputHash,
            'status' => ProjectStatusEnum::Generated,
        ]);

        $cost = $this->calculateCost($provider->name(), $response);

        Generation::create([
            'project_id' => $project->id,
            'input_hash' => $inputHash,
            'prompt_tokens' => $response->inputTokens,
            'completion_tokens' => $response->outputTokens,
            'cache_read_tokens' => $response->cacheReadTokens,
            'cost_usd' => $cost,
            'model' => $response->model,
            'provider' => $provider->name(),
            'duration_ms' => $response->durationMs,
            'cached' => false,
            'created_at' => now(),
        ]);
    }

    private function tryCache(Project $project): bool
    {
        $inputHash = hash('sha256', json_encode($project->wizard_data));

        $cached = Project::where('user_id', $project->user_id)
            ->where('input_hash', $inputHash)
            ->whereNotNull('generation_output')
            ->where('id', '!=', $project->id)
            ->first();

        if (!$cached) {
            return false;
        }

        $project->update([
            'generation_output' => $cached->generation_output,
            'input_hash' => $inputHash,
            'status' => ProjectStatusEnum::Generated,
        ]);

        Generation::create([
            'project_id' => $project->id,
            'input_hash' => $inputHash,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cache_read_tokens' => 0,
            'cost_usd' => 0,
            'model' => 'cache',
            'provider' => 'cache',
            'duration_ms' => 0,
            'cached' => true,
            'created_at' => now(),
        ]);

        return true;
    }

    private function buildSystemPrompt(Project $project): string
    {
        $base = file_get_contents(app_path('Prompts/system-prompt.md'));

        $templateContext = '';
        if ($project->template_slug) {
            $contextPath = storage_path("app/templates/{$project->template_slug}/prompt-context.md");
            if (file_exists($contextPath)) {
                $templateContext = file_get_contents($contextPath);
            }
        }

        return $base . ($templateContext ? "\n\n" . $templateContext : '');
    }

    public function buildUserMessage(Project $project): string
    {
        $data = $project->wizard_data ?? [];
        $describe = $data['step_describe'] ?? [];
        $users = $data['step_users'] ?? [];
        $models = $data['step_models'] ?? [];
        $auth = $data['step_auth'] ?? [];
        $integrations = $data['step_integrations'] ?? [];

        $lines = [];

        $lines[] = "## Project";
        $lines[] = "Name: " . ($describe['name'] ?? 'Untitled');
        $lines[] = "Description: " . ($describe['description'] ?? '');
        $lines[] = "Problem it solves: " . ($describe['problem'] ?? '');
        $lines[] = '';

        $lines[] = "## App Type";
        $lines[] = $users['app_type'] ?? 'not specified';
        $lines[] = '';

        $lines[] = "## User Roles";
        foreach ($users['roles'] ?? [] as $role) {
            $lines[] = "- " . ($role['name'] ?? 'unnamed') . ": " . ($role['description'] ?? '');
        }
        $lines[] = '';

        $lines[] = "## Core Models";
        foreach ($models['models'] ?? [] as $model) {
            $locked = ($model['locked'] ?? false) ? ' (locked - required)' : '';
            $lines[] = "### " . ($model['name'] ?? 'Unnamed') . $locked;
            $lines[] = $model['description'] ?? '';
            $lines[] = "Fields:";
            foreach ($model['fields'] ?? [] as $field) {
                $lines[] = "- " . ($field['name'] ?? '') . " (" . ($field['type'] ?? 'string') . ")";
            }
            $lines[] = '';
        }

        $lines[] = "## Authentication & Tenancy";
        $lines[] = "- Multi-tenant: " . (($auth['multi_tenant'] ?? false) ? 'Yes' : 'No');
        $lines[] = "- Auth method: " . ($auth['auth_method'] ?? 'sanctum');
        $guestAccess = $auth['guest_access'] ?? false;
        $lines[] = "- Guest access: " . ($guestAccess ? "Yes - " . ($auth['guest_description'] ?? '') : 'No');
        $lines[] = '';

        $lines[] = "## Integrations";
        $lines[] = "Selected: " . implode(', ', $integrations['selected'] ?? []);
        if (!empty($integrations['notes'])) {
            $lines[] = "Notes: " . $integrations['notes'];
        }

        return implode("\n", $lines);
    }

    private function calculateCost(string $provider, AiResponse $response): float
    {
        $input = $response->inputTokens;
        $output = $response->outputTokens;
        $cacheRead = $response->cacheReadTokens;
        $nonCached = $input - $cacheRead;

        return match ($provider) {
            'anthropic' => ($nonCached / 1_000_000 * 3) + ($cacheRead / 1_000_000 * 0.30) + ($output / 1_000_000 * 15),
            'gemini' => ($nonCached / 1_000_000 * 1.25) + ($cacheRead / 1_000_000 * 0.125) + ($output / 1_000_000 * 10),
            default => ($input / 1_000_000 * 3) + ($output / 1_000_000 * 15),
        };
    }
}
