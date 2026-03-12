<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ApiKeyController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\GenerationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LaravelAICoder API Routes — V1
|--------------------------------------------------------------------------
*/

// ── Public ──
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login',    [AuthController::class, 'login']);

    // Flutterwave webhook (must be public — FLW calls this)
    Route::post('/billing/webhook', [BillingController::class, 'webhook']);
});

// ── Authenticated ──
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/auth/me',    [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // User API Keys (BYOK)
    Route::get('/user/api-keys',        [ApiKeyController::class, 'index']);
    Route::post('/user/api-keys',       [ApiKeyController::class, 'store']);
    Route::put('/user/api-keys/{id}',   [ApiKeyController::class, 'update']);
    Route::delete('/user/api-keys/{id}',[ApiKeyController::class, 'destroy']);

    // Billing
    Route::post('/billing/subscribe',   [BillingController::class, 'subscribe']);
    Route::post('/billing/verify',      [BillingController::class, 'verify']);
    Route::get('/billing/portal',       [BillingController::class, 'portal']);
    Route::get('/billing/plans',        [BillingController::class, 'plans']);

    // Workspaces
    Route::apiResource('workspaces', WorkspaceController::class);

    // Projects (nested under workspaces + standalone)
    Route::get('/workspaces/{workspace}/projects', [ProjectController::class, 'indexByWorkspace']);
    Route::apiResource('projects', ProjectController::class);

    // Generations
    Route::post('/projects/{project}/generations', [GenerationController::class, 'store']);
    Route::get('/generations/{generation}',         [GenerationController::class, 'show']);
    Route::get('/generations/{generation}/stream',  [GenerationController::class, 'stream']);

    // Project files
    Route::get('/projects/{project}/files',          [ProjectController::class, 'files']);
    Route::get('/projects/{project}/files/{path}',   [ProjectController::class, 'fileContent'])
        ->where('path', '.*');

    // UI Upload
    Route::post('/projects/{project}/ui-upload',     [ProjectController::class, 'uiUpload']);

    // Plugin installer
    Route::post('/projects/{project}/plugins',       [ProjectController::class, 'installPlugin']);
    Route::get('/projects/{project}/plugins',        [ProjectController::class, 'plugins']);
});
