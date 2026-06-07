<?php

use App\Http\Controllers\Api\V1\ExternalTaskController;
use App\Http\Controllers\Api\V1\LocationDistanceController;
use App\Http\Controllers\Api\V1\OfflinePlanningController;
use App\Http\Controllers\Api\V1\OfflineSyncController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TravelTimeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Travel time calculation - accessible without auth for better UX
Route::prefix('v1')->group(function () {
    Route::post('/travel-times/calculate', [TravelTimeController::class, 'calculate']);
    Route::post('/travel-times/sequence', [TravelTimeController::class, 'calculateSequence']);

    // Location distances - cached database distances for better performance
    Route::get('/location-distances/stats', [LocationDistanceController::class, 'getCacheStats']);
    Route::get('/location-distances/{locationId}/sorted', [LocationDistanceController::class, 'getSortedDistances']);
    Route::post('/location-distances/sort', [LocationDistanceController::class, 'sortLocationsByDistance']);
    Route::get('/location-distances/{fromLocationId}/to/{toLocationId}', [LocationDistanceController::class, 'getDistanceBetween']);
    Route::post('/location-distances/{fromLocationId}/to/{toLocationId}/recalculate', [LocationDistanceController::class, 'recalculateDistance']);
});

Route::prefix('v1/external')->middleware(['external_api'])->group(function () {
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::post('/external-tasks', [ExternalTaskController::class, 'store']);
});

// Offline API endpoints - using web auth for session-based authentication
Route::prefix('v1/offline')->middleware(['web', 'auth'])->name('api.offline.')->group(function () {
    Route::get('/planning/{planning}/full', [OfflinePlanningController::class, 'getFullPlanningData']);
    Route::get('/planning/{planning}/sync-status', [OfflinePlanningController::class, 'checkSyncStatus']);
    Route::post('/sync/planning-tasks', [OfflineSyncController::class, 'syncPlanningTasks']);
    Route::post('/sync/photos', [OfflineSyncController::class, 'syncPhotos']);
    Route::get('/sync/status', [OfflineSyncController::class, 'getSyncStatus']);
});
