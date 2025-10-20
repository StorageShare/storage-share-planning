<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
            'can_execute_plannings' => \App\Http\Middleware\CanExecutePlannings::class,
            'can_manage_plannings' => \App\Http\Middleware\CanManagePlannings::class,
        ]);

        // Voeg de ClearValidationErrors middleware toe aan web group
        $middleware->web(append: [
            \App\Http\Middleware\ClearValidationErrors::class,
        ]);
    })
    ->withProviders([
        App\Providers\EventServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
