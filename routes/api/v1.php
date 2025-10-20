<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\PayloadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API v1 routes for your application.
|
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Public payload routes (for testing/IoT devices)
Route::post('/payloads', [PayloadController::class, 'store']);
Route::get('/payloads', [PayloadController::class, 'index']);
Route::get('/payloads/latest', [PayloadController::class, 'latest']);
Route::get('/payloads/{payload}', [PayloadController::class, 'show']);
Route::put('/payloads/{payload}', [PayloadController::class, 'update']);
Route::delete('/payloads/{payload}', [PayloadController::class, 'destroy']);

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
