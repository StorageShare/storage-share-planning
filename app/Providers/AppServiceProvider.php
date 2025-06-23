<?php

namespace App\Providers;

use App\Models\Location;
use App\Observers\LocationObserver;
use App\Services\ImageService;
use App\Services\TravelTimeService;
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
    }
}
