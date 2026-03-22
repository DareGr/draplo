<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\Wizard\WizardController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/templates', [TemplateController::class, 'index']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/auth/me', function () {
        return response()->json(auth()->user());
    });

    // Wizard
    Route::post('/wizard/projects', [WizardController::class, 'store']);
    Route::get('/wizard/projects/{project}', [WizardController::class, 'show']);
    Route::put('/wizard/projects/{project}', [WizardController::class, 'update']);
    Route::post('/wizard/projects/{project}/suggest', [WizardController::class, 'suggest'])
        ->middleware('throttle:20,1');

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    // Generation
    Route::post('/projects/{project}/generate', [GenerationController::class, 'generate'])
        ->middleware('rate_limit_generation');
    Route::get('/projects/{project}/generation', [GenerationController::class, 'status']);
    Route::post('/projects/{project}/regenerate', [GenerationController::class, 'regenerate'])
        ->middleware('rate_limit_generation');
    Route::get('/projects/{project}/preview', [GenerationController::class, 'preview']);
    Route::get('/projects/{project}/preview/{filepath}', [GenerationController::class, 'previewFile'])
        ->where('filepath', '.*');
    Route::put('/projects/{project}/preview/{filepath}', [GenerationController::class, 'updatePreviewFile'])
        ->where('filepath', '.*');

    // Admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/settings', [AdminController::class, 'settings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);
        Route::get('/stats', [AdminController::class, 'stats']);
    });
});
