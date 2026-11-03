<?php

namespace App\Providers;

use App\Console\Commands\MongoFresh;
use App\Database\MongoConnection;
use App\Database\MongoQueueConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        DB::extend('mongodb', fn ($config) => new MongoConnection($config));
        $this->commands([MongoFresh::class]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app['queue']->addConnector('database', fn () => new MongoQueueConnector($this->app['db']));
    }
}
