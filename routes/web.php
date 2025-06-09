<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LocationSyncController;
use App\Http\Controllers\DefaultTaskController;
use App\Http\Controllers\TaskBacklogController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\PlanningTaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');

Route::middleware(['auth', 'is_admin'])->group(function () {
    Route::post('locations/sync', [LocationSyncController::class, 'syncNow'])->name('locations.sync');
    Route::resource('users', UserController::class);
    Route::resource('locations', LocationController::class);
    Route::resource('default-tasks', DefaultTaskController::class);
    Route::get('backlog', [TaskBacklogController::class, 'index'])->name('backlog.index');
});

Route::middleware('auth')->group(function () {
    Route::resource('plannings', PlanningController::class);

    // Routes for completing and uncompleting a task within a planning
    Route::post('plannings/{planning}/tasks/{planning_task}/complete', [PlanningTaskController::class, 'complete'])->name('plannings.tasks.complete');
    Route::post('plannings/{planning}/tasks/{planning_task}/uncomplete', [PlanningTaskController::class, 'uncomplete'])->name('plannings.tasks.uncomplete');

    Route::get('tasks/select-location', [TaskController::class, 'selectLocationForTask'])->name('tasks.select-location');
    Route::resource('locations.tasks', TaskController::class)->shallow();
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
