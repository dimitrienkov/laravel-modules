<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Testing\PendingCommand;

/**
 * Using class must use CreatesLifecycleEnvironment and define $tempDir, $stateRoot, $app.
 *
 * @property \Illuminate\Foundation\Application $app
 */
trait RegistersLifecycleCommands
{
    /**
     * @return array{config: Repository, stateRepo: ModuleStateRepository, manifests: ModuleManifestRepository, cache: ModuleRegistryCache, registry: ModuleRegistry}
     */
    protected function registerCoreLifecycleServices(?string $backupPath = null): array
    {
        $config = $this->lifecycleConfig(backupPath: $backupPath);
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        $this->app->instance(ModuleRegistryInterface::class, $registry);
        $this->app->instance(ModuleManifestRepositoryInterface::class, $manifests);
        $this->app->instance(ModuleStateRepositoryInterface::class, $stateRepo);
        $this->app->instance(ModuleDependencyGuard::class, $this->lifecycleDependencyGuard($registry));
        $this->app->instance(LifecycleRegistryInvalidator::class, $this->lifecycleInvalidator($cache, $registry));

        return compact('config', 'stateRepo', 'manifests', 'cache', 'registry');
    }

    /**
     * @param class-string $commandClass
     */
    protected function registerArtisanCommand(string $commandClass): void
    {
        $this->app->make(Kernel::class)->registerCommand($this->app->make($commandClass));
    }

    protected function artisanCommand(string $command): PendingCommand
    {
        $result = $this->artisan($command);
        \assert($result instanceof PendingCommand);

        return $result;
    }
}
