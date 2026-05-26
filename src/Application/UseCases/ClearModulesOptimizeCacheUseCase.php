<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\ClearModulesOptimizeCacheResult;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;

final readonly class ClearModulesOptimizeCacheUseCase
{
    public function __construct(
        private ModuleRegistryCacheInterface $cache,
        private LifecycleRegistryInvalidator $invalidator,
    ) {
    }

    public function execute(): ClearModulesOptimizeCacheResult
    {
        if (! $this->cache->exists()) {
            return new ClearModulesOptimizeCacheResult(cleared: false);
        }

        $this->invalidator->flushAndReset();

        return new ClearModulesOptimizeCacheResult(cleared: true);
    }
}
