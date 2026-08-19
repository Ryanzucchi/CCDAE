<?php

namespace App\Providers;

use App\Services\Internet\Contracts\AntennaProvider;
use App\Services\Internet\OverpassAntennaProvider;
use App\Services\Osm\CommercialBuildingProvider;
use App\Services\Osm\Contracts\BuildingProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BuildingProvider::class, CommercialBuildingProvider::class);
        $this->app->bind(AntennaProvider::class, OverpassAntennaProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::looping(function () {
            Cache::put('queue_worker_last_seen', now()->timestamp, 300);
        });

        Queue::before(function () {
            Cache::put('queue_worker_last_seen', now()->timestamp, 300);
        });

        Queue::after(function () {
            Cache::put('queue_worker_last_seen', now()->timestamp, 300);
        });
    }
}
