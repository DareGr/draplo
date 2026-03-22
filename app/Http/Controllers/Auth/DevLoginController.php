<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DevLoginController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (app()->environment('production')) {
            abort(403, 'Dev login is not available in production.');
        }

        $user = User::where('email', 'dev@draplo.test')->firstOrFail();
        $token = $user->createToken('dev-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
}
