<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\OptimizeModulesResult;
use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistrySnapshotBuilder;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;

final readonly class OptimizeModulesUseCase
{
    public function __construct(
        private ModuleRegistrySnapshotBuilder $builder,
        private ModuleRegistryCacheInterface $cache,
        private ModuleDiagnosticsInterface $diagnostics = new NullModuleDiagnostics(),
    ) {
    }

    public function execute(): OptimizeModulesResult
    {
        $this->diagnostics->lifecycleStarted(LifecycleOperation::Optimize);

        $snapshot = $this->builder->build();
        $cachePath = $this->cache->write($snapshot->all());

        $this->diagnostics->lifecycleSucceeded(LifecycleOperation::Optimize);

        return new OptimizeModulesResult(
            path: $cachePath,
            count: $snapshot->count(),
        );
    }
}
