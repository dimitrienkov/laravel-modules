<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Providers;

use DimitrienkoV\LaravelModules\Services\ConfigLoaderService;
use DimitrienkoV\LaravelModules\Services\FactoryLoaderService;
use DimitrienkoV\LaravelModules\Services\MigrationLoaderService;
use DimitrienkoV\LaravelModules\Services\MoonShineLoaderService;
use DimitrienkoV\LaravelModules\Services\RouteLoaderService;
use DimitrienkoV\LaravelModules\Services\ServiceProviderLoaderService;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ModuleLoaderServiceProvider extends ServiceProvider
{
    /**
     * @throws Throwable
     */
    public function boot(
        RouteLoaderService          $routeLoader,
        MigrationLoaderService      $migrationLoader,
        FactoryLoaderService        $factoryLoader,
        ConfigLoaderService         $configLoader,
        MoonShineLoaderService      $moonShineLoader,
        ServiceProviderLoaderService $serviceProviderLoader,
    ): void {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/modules.php',
            'modules'
        );

        $this->publishes([
            __DIR__ . '/../../config/modules.php' => config_path('modules.php'),
        ], 'config');

        $configLoader->autoload();
        $factoryLoader->autoload();
        $migrationLoader->autoload();
        $routeLoader->autoload();
        $moonShineLoader->autoload();
        $serviceProviderLoader->autoload();
    }
}
