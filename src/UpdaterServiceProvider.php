<?php

declare(strict_types=1);

namespace Codedge\Updater;

use Codedge\Updater\Commands\CheckForUpdate;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\Notifications\EventHandler;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Illuminate\Support\ServiceProvider;

class UpdaterServiceProvider extends ServiceProvider
{
    protected bool $defer = false;

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/self-update.php' => config_path('self-update.php'),
        ], 'config');

        $this->loadViews();
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'self-update');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/self-update'),
        ]);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/self-update.php', 'self-update');

        $this->app['events']->subscribe(EventHandler::class);

        $this->registerCommands();
        $this->registerManager();
    }

    protected function registerCommands(): void
    {
        $this->commands([
            CheckForUpdate::class,
        ]);

        // Register custom commands from config
        collect(config('self-update.artisan_commands.pre_update'))->each(function ($command) {
            $this->commands([$command['class']]);
        });
        collect(config('self-update.artisan_commands.post_update'))->each(function ($command) {
            $this->commands([$command['class']]);
        });
    }

    protected function registerManager(): void
    {
        $this->app->singleton('updater', function () {
            return new UpdaterManager(app());
        });

        $this->app->bind(GithubRepositoryType::class, function (): GithubRepositoryType {
            return new GithubRepositoryType(
                config('self-update.repository_types.github'),
                $this->app->make(UpdateExecutor::class)
            );
        });

        $this->app->alias('updater', UpdaterManager::class);
    }

    public function provides(): array
    {
        return [
            'updater',
        ];
    }
}
