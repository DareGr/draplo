<?php

use Illuminate\Support\Facades\Route;

// Landing page (Blade, for SEO — Phase 5)
Route::get('/', function () {
    return view('landing');
});

// React SPA catch-all — serves app.blade.php for all SPA routes
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api|dev|sanctum|horizon).*$');
