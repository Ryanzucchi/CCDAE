<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
