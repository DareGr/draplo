<?php

namespace App\Http\Middleware;

use App\Enums\ProjectStatusEnum;
use App\Models\Generation;
use App\Models\Project;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitGeneration
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()->id;
        $limit = (int) app(SettingsService::class)->get('generation_rate_limit', 5);

        $completedCount = Generation::whereHas('project', fn($q) => $q->where('user_id', $userId))
            ->where('created_at', '>', now()->subHour())
            ->count();

        $inFlightCount = Project::where('user_id', $userId)
            ->where('status', ProjectStatusEnum::Generating)
            ->count();

        if (($completedCount + $inFlightCount) >= $limit) {
            return response()->json([
                'message' => "Rate limit exceeded. Maximum {$limit} generations per hour.",
            ], 429)->withHeaders(['Retry-After' => '3600']);
        }

        return $next($request);
    }
}
