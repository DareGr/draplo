<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function flags(): JsonResponse
    {
        return response()->json([
            'stripe_enabled' => (bool) config('app.flags.stripe', true),
            'coolify_enabled' => (bool) config('app.flags.coolify', true),
            'github_enabled' => (bool) config('app.flags.github', true),
            'premium_templates_enabled' => (bool) config('app.flags.premium_templates', true),
            'threejs_hero_enabled' => (bool) config('app.flags.threejs_hero', true),
            'byos_hetzner_enabled' => (bool) config('app.flags.byos_hetzner', true),
        ]);
    }
}
