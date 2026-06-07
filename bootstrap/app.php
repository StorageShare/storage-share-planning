<?php

use App\Http\Middleware\CanExecutePlannings;
use App\Http\Middleware\CanManagePlannings;
use App\Http\Middleware\IncreaseExecutionTime;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\VerifyExternalApiSignature;
use App\Providers\EventServiceProvider;
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
        // Global HTTP middleware
        $middleware->append(IncreaseExecutionTime::class);

        $middleware->alias([
            'is_admin' => IsAdmin::class,
            'can_execute_plannings' => CanExecutePlannings::class,
            'can_manage_plannings' => CanManagePlannings::class,
            'external_api' => VerifyExternalApiSignature::class,
        ]);

    })
    ->withProviders([
        EventServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
