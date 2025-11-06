<?php

namespace DanOgbo\AutoPay;

use Illuminate\Support\ServiceProvider;

class AutoPayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/autopay.php',
            'autopay'
        );

        $this->app->singleton('autopay', function ($app) {
            return new AutoPayManager($app);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/autopay.php' => config_path('autopay.php'),
        ], 'autopay-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'autopay-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
