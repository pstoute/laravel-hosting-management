<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting;

use Illuminate\Support\ServiceProvider;

class HostingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/hosting.php',
            'hosting'
        );

        $this->app->singleton('hosting', function ($app) {
            return new HostingManager($app);
        });

        $this->app->alias('hosting', HostingManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/hosting.php' => config_path('hosting.php'),
            ], 'hosting-config');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'hosting',
            HostingManager::class,
        ];
    }
}
