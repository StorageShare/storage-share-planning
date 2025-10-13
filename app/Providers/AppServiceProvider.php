<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Location;
use App\Observers\LocationObserver;
use App\Services\ImageService;
use App\Services\TravelTimeService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TravelTimeService::class);
        $this->app->singleton(ImageService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Location::observe(LocationObserver::class);

        Blade::if('role', function ($role) {
            if (!auth()->check()) return false;
            // Accept either enum instance or string (e.g. 'admin')
            $target = $role instanceof Role ? $role : Role::from($role);
            return auth()->user()->role === $target;
        });

        Blade::if('anyrole', function (...$roles) {
            if (!auth()->check()) return false;
            $userRole = auth()->user()->role;
            $targets = array_map(fn($r) => $r instanceof Role ? $r : Role::from($r), $roles);
            return in_array($userRole, $targets, true);
        });
    }
}
