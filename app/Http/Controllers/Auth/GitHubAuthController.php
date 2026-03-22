<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GitHubAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['repo', 'user:email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $user = User::updateOrCreate(
            ['github_id' => (string) $githubUser->getId()],
            [
                'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'email' => $githubUser->getEmail(),
                'github_username' => $githubUser->getNickname(),
                'avatar_url' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
            ]
        );

        $token = $user->createToken('github-auth')->plainTextToken;

        return redirect("/auth/callback#token={$token}");
    }
}
