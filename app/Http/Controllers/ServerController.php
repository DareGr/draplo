<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionServerJob;
use App\Models\ServerConnection;
use App\Services\Deploy\CoolifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ServerController extends Controller
{
    public function index(): JsonResponse
    {
        $servers = auth()->user()->serverConnections()
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($servers);
    }

    public function store(Request $request, CoolifyService $coolifyService): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:hetzner,manual',
            'server_name' => 'required|string|max:255',
        ]);

        $provider = $request->input('provider');

        if ($provider === 'hetzner') {
            $request->validate([
                'api_key' => 'required|string',
                'server_spec' => 'nullable|string|max:100',
            ]);

            $server = auth()->user()->serverConnections()->create([
                'provider' => 'hetzner',
                'encrypted_api_key' => $request->input('api_key'),
                'server_name' => $request->input('server_name'),
                'server_spec' => $request->input('server_spec', 'cx22'),
                'status' => 'pending',
            ]);

            ProvisionServerJob::dispatch($server);

            return response()->json($server, 202);
        }

        // Manual provider
        $request->validate([
            'coolify_url' => 'required|url|max:500',
            'coolify_api_key' => 'required|string',
            'server_ip' => 'nullable|string|max:45',
        ]);

        $coolifyUrl = $request->input('coolify_url');
        $coolifyApiKey = $request->input('coolify_api_key');

        $healthy = $coolifyService->healthcheck($coolifyUrl, $coolifyApiKey);

        if (! $healthy) {
            return response()->json([
                'message' => 'Could not connect to Coolify. Please verify the URL and API key.',
            ], 422);
        }

        $server = auth()->user()->serverConnections()->create([
            'provider' => 'manual',
            'server_name' => $request->input('server_name'),
            'server_ip' => $request->input('server_ip'),
            'coolify_url' => $coolifyUrl,
            'coolify_api_key' => $coolifyApiKey,
            'status' => 'active',
            'last_health_check' => now(),
        ]);

        return response()->json($server, 201);
    }

    public function show(ServerConnection $server): JsonResponse
    {
        if ($server->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        return response()->json($server);
    }

    public function destroy(ServerConnection $server): Response
    {
        if ($server->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $server->delete();

        return response()->noContent();
    }

    public function health(ServerConnection $server, CoolifyService $coolifyService): JsonResponse
    {
        if ($server->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if (! $server->coolify_url || ! $server->coolify_api_key) {
            return response()->json(['healthy' => false]);
        }

        $healthy = $coolifyService->healthcheck($server->coolify_url, $server->coolify_api_key);

        if ($healthy) {
            $server->update(['last_health_check' => now()]);
        }

        return response()->json(['healthy' => $healthy]);
    }
}
