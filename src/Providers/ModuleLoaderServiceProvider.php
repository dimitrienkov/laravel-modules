<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Providers;

use DimitrienkoV\LaravelModules\Console\Commands\MakeModule;
use DimitrienkoV\LaravelModules\Services\ConfigLoaderService;
use DimitrienkoV\LaravelModules\Services\FactoryLoaderService;
use DimitrienkoV\LaravelModules\Services\MigrationLoaderService;
use DimitrienkoV\LaravelModules\Services\RouteLoaderService;
use Illuminate\Support\ServiceProvider;

class ModuleLoaderServiceProvider extends ServiceProvider
{
    public function boot(
        RouteLoaderService     $routeLoader,
        MigrationLoaderService $migrationLoader,
        FactoryLoaderService   $factoryLoader,
        ConfigLoaderService    $configLoader,
    ): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/modules.php',
            'modules'
        );

        $this->publishes([
            __DIR__ . '/../../config/modules.php' => config_path('modules.php'),
        ], 'config');

        $configLoader->loadConfigs();
        $factoryLoader->configureFactoryNameResolver();
        $migrationLoader->loadMigrations();
        $routeLoader->loadRoutes();
    }

    public function register(): void
    {
        $this->commands([
            MakeModule::class,
        ]);
    }
}
