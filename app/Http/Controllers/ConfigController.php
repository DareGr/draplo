<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function flags(): JsonResponse
    {
        return response()->json([
            'coolify_enabled' => (bool) config('app.flags.coolify', true),
            'github_enabled' => (bool) config('app.flags.github', true),
            'templates_enabled' => (bool) config('app.flags.templates', true),
            'threejs_hero_enabled' => (bool) config('app.flags.threejs_hero', true),
            'byos_hetzner_enabled' => (bool) config('app.flags.byos_hetzner', true),
        ]);
    }
}
