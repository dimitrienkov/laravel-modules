<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Providers;

use DimitrienkoV\LaravelModules\Console\Commands\ModulesOptimizeClearCommand;
use DimitrienkoV\LaravelModules\Console\Commands\ModulesOptimizeCommand;
use DimitrienkoV\LaravelModules\Services\ConfigLoaderService;
use DimitrienkoV\LaravelModules\Services\FactoryLoaderService;
use DimitrienkoV\LaravelModules\Services\MigrationLoaderService;
use DimitrienkoV\LaravelModules\Services\MoonShineLoaderService;
use DimitrienkoV\LaravelModules\Services\RouteLoaderService;
use DimitrienkoV\LaravelModules\Services\ServiceProviderLoaderService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;

final class ModuleLoaderServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/modules.php'),
            'modules'
        );

        $this->app->make(ConfigLoaderService::class)->autoload();

        // Регистрируем MoonShine ресурсы после резолва CoreContract
        $this->app->afterResolving(CoreContract::class, function () {
            $this->app->make(MoonShineLoaderService::class)->autoload();
        });
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(
        MigrationLoaderService       $migrationLoader,
        RouteLoaderService           $routeLoader,
        FactoryLoaderService         $factoryLoader,
        ServiceProviderLoaderService $serviceProviderLoader,
        ConfigLoaderService          $configLoader,
    ): void {
        $this->publishes([
            base_path('config/modules.php') => config_path('modules.php'),
        ], 'modules-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModulesOptimizeCommand::class,
                ModulesOptimizeClearCommand::class,
            ]);

            $this->optimizes(
                optimize: 'modules:optimize',
                clear: 'modules:optimize-clear'
            );
        }

        $configLoader->autoload();
        $factoryLoader->autoload();
        $migrationLoader->autoload();
        $routeLoader->autoload();
        $serviceProviderLoader->autoload();
    }
}
