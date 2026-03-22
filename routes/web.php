<?php

use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\GitHubAuthController;
use Illuminate\Support\Facades\Route;

// Landing page (Blade, for SEO — Phase 5)
Route::get('/', function () {
    return view('landing');
});

// Dev-mode login (non-production only)
Route::get('/dev/login', DevLoginController::class);

// GitHub OAuth
Route::get('/auth/github', [GitHubAuthController::class, 'redirect']);
Route::get('/auth/github/callback', [GitHubAuthController::class, 'callback']);

// React SPA catch-all — serves app.blade.php for all SPA routes
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api|dev|auth|sanctum|horizon).*$');
