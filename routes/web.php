<?php

use App\Http\Controllers\Admin\TaskReviewController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BenodigdheidController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefaultTaskController;
use App\Http\Controllers\EndChecklistController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LocationSyncController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PlanningTaskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskBacklogController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyPlanningController;

Route::get('/', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');

Route::middleware(['auth', 'is_admin'])->group(function () {
    Route::get('tasks/review', [TaskReviewController::class, 'index'])->name('admin.tasks.review');
    Route::get('tasks/review/{type}/{id}', [TaskReviewController::class, 'show'])->name('admin.tasks.show');
    Route::post('tasks/review-skipped/{planning_task}', [TaskReviewController::class, 'reviewSkipped'])->name('admin.tasks.review-skipped');
    Route::post('locations/sync', [LocationSyncController::class, 'syncNow'])->name('locations.sync');
    Route::resource('users', UserController::class);
    Route::resource('locations', LocationController::class);
    Route::resource('default-tasks', DefaultTaskController::class);
    Route::resource('benodigdheden', BenodigdheidController::class);
    Route::get('backlog', [TaskBacklogController::class, 'index'])->name('backlog.index');
    
    // CSV Import routes
    Route::get('csv-import', [CsvImportController::class, 'show'])->name('csv-import.index');
    Route::post('csv-import', [CsvImportController::class, 'import'])->name('csv-import.import');
    Route::get('csv-import/template', [CsvImportController::class, 'downloadTemplate'])->name('csv-import.template');

    Route::post('tasks/{task}/approve', [TaskController::class, 'approve'])->name('tasks.approve');
    Route::post('tasks/{task}/reject', [TaskController::class, 'reject'])->name('tasks.reject');

    Route::post('plannings/tasks/{planning_task}/approve', [PlanningTaskController::class, 'approve'])->name('plannings.tasks.approve');
    Route::post('plannings/tasks/{planning_task}/reject', [PlanningTaskController::class, 'reject'])->name('plannings.tasks.reject');

    // End checklist admin routes
    Route::get('end-checklists/pending', [EndChecklistController::class, 'pendingReviews'])->name('admin.end-checklists.pending');
    Route::post('end-checklist-items/{item}/review', [EndChecklistController::class, 'review'])->name('admin.end-checklist-items.review');
    Route::post('end-checklist-items/{item}/approve', [EndChecklistController::class, 'approveItem'])->name('admin.end-checklist.approve');
    Route::get('end-checklist-items/{item}/reject', [EndChecklistController::class, 'showRejectForm'])->name('admin.end-checklist.reject');
    Route::post('end-checklist-items/{item}/reject', [EndChecklistController::class, 'rejectItem'])->name('admin.end-checklist.reject.process');

    // Only admins can create, edit, and delete plannings
    Route::get('plannings/create', [PlanningController::class, 'create'])->name('plannings.create');
    Route::post('plannings', [PlanningController::class, 'store'])->name('plannings.store');
    Route::get('plannings/{planning}/edit', [PlanningController::class, 'edit'])->name('plannings.edit');
    Route::put('plannings/{planning}', [PlanningController::class, 'update'])->name('plannings.update');
    Route::patch('plannings/{planning}', [PlanningController::class, 'update']);
    Route::delete('plannings/{planning}', [PlanningController::class, 'destroy'])->name('plannings.destroy');
    Route::post('plannings/{planning}/send-notifications', [PlanningController::class, 'sendNotifications'])->name('plannings.send-notifications');
    
    // Only admins can view plannings via /plannings routes
    Route::resource('plannings', PlanningController::class)->only(['index', 'show']);
});

Route::middleware('auth')->group(function () {

    Route::get('my-planning', [MyPlanningController::class, 'show'])->name('my-planning.show');
    Route::get('my-planning/{planning}', [MyPlanningController::class, 'show'])->name('my-planning.planning');

    // Routes for completing and uncompleting a task within a planning
    Route::post('plannings/{planning}/tasks/{planning_task}/complete', [PlanningTaskController::class, 'complete'])->name('plannings.tasks.complete');
    Route::post('plannings/{planning}/tasks/{planning_task}/uncomplete', [PlanningTaskController::class, 'uncomplete'])->name('plannings.tasks.uncomplete');
    
    // Route for the step-by-step completion form
    Route::post('plannings/{planning}/tasks/{planning_task}/submit-completion', [PlanningTaskController::class, 'submitCompletion'])->name('plannings.tasks.submit-completion');

    // Route for skipping a task
    Route::post('plannings/{planning}/tasks/{planning_task}/skip', [PlanningTaskController::class, 'skip'])->name('plannings.tasks.skip');

    // Route for reopening a task
    Route::post('plannings/{planning}/tasks/{planning_task}/reopen', [PlanningTaskController::class, 'reopen'])->name('plannings.tasks.reopen');

    Route::get('plannings/tasks/{planning_task}', [PlanningTaskController::class, 'show'])->name('plannings.tasks.show');

    // End checklist routes
    Route::get('plannings/{planning}/end-checklist', [EndChecklistController::class, 'index'])->name('plannings.end-checklist.index');
    Route::post('plannings/{planning}/end-checklist', [EndChecklistController::class, 'create'])->name('plannings.end-checklist.create');
    Route::post('plannings/{planning}/end-checklist/submit', [EndChecklistController::class, 'submit'])->name('plannings.end-checklist.submit');
    Route::post('end-checklist-items/{item}/upload-photo', [EndChecklistController::class, 'uploadPhoto'])->name('end-checklist-items.upload-photo');
    Route::delete('end-checklist-items/{item}/photo', [EndChecklistController::class, 'deletePhoto'])->name('end-checklist-items.delete-photo');

    Route::get('tasks/select-location', [TaskController::class, 'selectLocationForTask'])->name('tasks.select-location');
    Route::resource('locations.tasks', TaskController::class)->shallow();
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/plannings/{planning}/locations/{location}/timer', [PlanningController::class, 'getLocationTimer']);
    Route::post('/plannings/{planning}/locations/{location}/timer/start', [PlanningController::class, 'startLocationTimer']);
    Route::post('/plannings/{planning}/locations/{location}/timer/stop', [PlanningController::class, 'stopLocationTimer']);
    Route::post('/plannings/{planning}/locations/{location}/timer/restart', [PlanningController::class, 'restartLocationTimer']);

    // Admin timer routes
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/timers', [\App\Http\Controllers\Admin\TimerController::class, 'index'])->name('timers.index');
        Route::get('/timers/{planning}', [\App\Http\Controllers\Admin\TimerController::class, 'show'])->name('timers.show');
        Route::get('/timers/{planning}/live-data', [\App\Http\Controllers\Admin\TimerController::class, 'getLiveData'])->name('timers.live-data');
        Route::get('/timers/export', [\App\Http\Controllers\Admin\TimerController::class, 'export'])->name('timers.export');
        Route::get('/bv-stats', [\App\Http\Controllers\Admin\BvStatsController::class, 'index'])->name('bv-stats.index');
        
        // Syslog routes
        Route::get('/logs/syslog', [\App\Http\Controllers\Admin\SyslogController::class, 'index'])->name('logs.syslog');
        Route::get('/logs/syslog/api', [\App\Http\Controllers\Admin\SyslogController::class, 'api'])->name('logs.syslog.api');
    });
});

Route::post('/deploy', function () {
    // Verificatie van Bitbucket payload
    $payload = request()->getContent();
    $signature = request()->header('X-Hub-Signature');
    
    // Voer deployment script uit
    $output = shell_exec('cd applications/cpbwahmrsn/public_html && bash deploy.sh 2>&1');
    
    return response()->json(['status' => 'success', 'output' => $output]);
});

require __DIR__.'/auth.php';
