<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\DefaultTaskController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\PlanningController as PlanningControllerV1;
use App\Http\Controllers\Api\V1\TaskPhotoController;

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

Route::prefix('v1')->group(function () {
    Route::apiResource('locations', LocationController::class);
    Route::apiResource('default-tasks', DefaultTaskController::class);

    // Taken per locatie (genest)
    // GET /locations/{location}/tasks
    // POST /locations/{location}/tasks
    Route::apiResource('locations.tasks', TaskController::class)->shallow();
    // Overige task routes (GET /tasks/{task}, PUT/PATCH /tasks/{task}, DELETE /tasks/{task})
    // komen door shallow() direct onder /v1/tasks/{task}

    Route::apiResource('plannings', PlanningControllerV1::class);

    // Specifieke routes voor Task Photos
    // POST /v1/tasks/{task}/photos - Upload foto voor een taak
    Route::post('/tasks/{task}/photos', [TaskPhotoController::class, 'store'])->name('tasks.photos.store');
    // DELETE /v1/task-photos/{task_photo} - Verwijder een specifieke foto (gebruik TaskPhoto model binding)
    Route::delete('/task-photos/{task_photo}', [TaskPhotoController::class, 'destroy'])->name('task-photos.destroy');

    // Aanvullende API routes voor planningen en taken uitvoeren:
    // Bijvoorbeeld: Een taak in een planning als voltooid markeren
    // Route::patch('/planning-tasks/{planning_task}/complete', [Api\V1\PlanningTaskStatusController::class, 'complete']);
    // Route::patch('/planning-tasks/{planning_task}/uncomplete', [Api\V1\PlanningTaskStatusController::class, 'uncomplete']);

    // Specifieke routes voor het beheren van taken binnen een planning (PlanningTasks)
    // Bijvoorbeeld: status wijzigen, foto's toevoegen aan een planning_task.
    // Dit vereist waarschijnlijk een nieuwe controller: Api/V1/PlanningTaskController

    // Voorbeeld: Update status van een planning taak
    // Route::patch('plannings/{planning}/tasks/{planning_task}/complete', [PlanningTaskControllerV1::class, 'complete']);
    // Route::patch('plannings/{planning}/tasks/{planning_task}/incomplete', [PlanningTaskControllerV1::class, 'incomplete']);

    // Voorbeeld: Foto's toevoegen aan een planning taak (denk aan TaskPhotoController logica, maar dan voor PlanningTask)
    // Route::post('planning-tasks/{planning_task}/photos', [PlanningTaskPhotoControllerV1::class, 'store']);
    // Route::delete('planning-tasks/{planning_task}/photos/{task_photo}', [PlanningTaskPhotoControllerV1::class, 'destroy']);
}); 