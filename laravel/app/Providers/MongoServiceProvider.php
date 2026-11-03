<?php

namespace App\Providers;

use App\Mongo\Connection;
use App\Mongo\NumericUserProvider;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class MongoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::provider('mongo-numeric', static fn ($app, array $config): NumericUserProvider => new NumericUserProvider($app['hash'], $config['model']));
    }

    public function register(): void
    {
        $this->app->extend('queue.failer', static function ($provider, $app) {
            if ($app['config']->get('queue.failed.driver') !== 'mongodb') {
                return $provider;
            }

            return new DatabaseUuidFailedJobProvider(
                $app['db'], $app['config']->get('queue.failed.database'), $app['config']->get('queue.failed.table'),
            );
        });
        $this->app->resolving('db', static function ($database): void {
            $database->extend('mongodb', static function (array $configuration, string $name): Connection {
                return new Connection($configuration + ['name' => $name]);
            });
        });
    }
}
