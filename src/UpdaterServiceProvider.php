<?php

namespace Codedge\Updater;

use Illuminate\Support\ServiceProvider;

/**
 * UpdaterServiceProvider.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class UpdaterServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Perform post-registration booting of services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/self-update.php' => config_path('self-update.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'self-update');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/self-update'),
        ]);

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/Http/routes.php';
        }
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/self-update.php', 'self-update');

        $this->app->singleton(Updater::class, function ($app) {
            return new Connection($app['config']['self-update']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['updater'];
    }
}
