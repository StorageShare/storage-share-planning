<?php

use App\Http\Controllers\Api\V1\DefaultTaskController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\LocationDistanceController;
use App\Http\Controllers\Api\V1\PlanningController as PlanningControllerV1;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskPhotoController;
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

// Offline API endpoints - using web auth for session-based authentication
Route::prefix('v1/offline')->middleware(['web', 'auth'])->name('api.offline.')->group(function () {
    Route::get('/planning/{planning}/full', [\App\Http\Controllers\Api\V1\OfflinePlanningController::class, 'getFullPlanningData']);
    Route::get('/planning/{planning}/sync-status', [\App\Http\Controllers\Api\V1\OfflinePlanningController::class, 'checkSyncStatus']);
    Route::post('/sync/planning-tasks', [\App\Http\Controllers\Api\V1\OfflineSyncController::class, 'syncPlanningTasks']);
    Route::post('/sync/photos', [\App\Http\Controllers\Api\V1\OfflineSyncController::class, 'syncPhotos']);
    Route::get('/sync/status', [\App\Http\Controllers\Api\V1\OfflineSyncController::class, 'getSyncStatus']);
});
