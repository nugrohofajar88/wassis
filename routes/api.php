<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Public webhook (verified via a shared secret in the URL, not Sanctum — see AGENTS.md)
Route::post('/webhooks/fonnte/{secret}', [WebhookController::class, 'fonnte']);

// Protected routes (require Sanctum token)
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/me',               [AuthController::class, 'me']);
        Route::post('/logout',          [AuthController::class, 'logout']);
        Route::post('/fcm-token',       [AuthController::class, 'updateFcmToken']);
    });

    // Contacts
    Route::apiResource('contacts', ContactController::class);

    // Messages & AI reply assistance (scoped to a contact)
    Route::prefix('contacts/{contact}')->group(function () {
        Route::get('/messages',              [MessageController::class, 'index']);
        Route::post('/messages',             [MessageController::class, 'store']);
        Route::post('/messages/import',      [MessageController::class, 'import']);
        Route::post('/suggest-reply',        [MessageController::class, 'suggestReply']);
        Route::post('/memories/analyze',     [MemoryController::class, 'analyze']);
    });

    // Memories
    Route::get('/memories',            [MemoryController::class, 'index']);
    Route::post('/memories',           [MemoryController::class, 'store']);
    Route::delete('/memories/{memory}', [MemoryController::class, 'destroy']);

    // Calendar events
    Route::apiResource('events', EventController::class);

    // Settings
    Route::get('/settings',              [SettingController::class, 'index']);
    Route::put('/settings',              [SettingController::class, 'update']);
    Route::delete('/settings/{key}',     [SettingController::class, 'destroy']);

});
