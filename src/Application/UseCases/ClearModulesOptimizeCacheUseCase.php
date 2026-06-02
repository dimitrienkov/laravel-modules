<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\ClearModulesOptimizeCacheResult;
use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use Throwable;

final readonly class ClearModulesOptimizeCacheUseCase
{
    public function __construct(
        private ModuleRegistryCacheInterface $cache,
        private LifecycleRegistryInvalidator $invalidator,
        private ModuleDiagnosticsInterface $diagnostics = new NullModuleDiagnostics(),
    ) {}

    public function execute(): ClearModulesOptimizeCacheResult
    {
        if (! $this->cache->exists()) {
            return new ClearModulesOptimizeCacheResult(cleared: false);
        }

        $this->diagnostics->lifecycleStarted(LifecycleOperation::ClearCache);

        try {
            $this->invalidator->flushAndReset();

            $this->diagnostics->lifecycleSucceeded(LifecycleOperation::ClearCache);

            return new ClearModulesOptimizeCacheResult(cleared: true);
        } catch (Throwable $e) {
            $this->diagnostics->lifecycleFailed(LifecycleOperation::ClearCache, exception: $e);

            throw $e;
        }
    }
}
