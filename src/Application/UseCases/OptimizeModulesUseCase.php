<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\OptimizeModulesResult;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistrySnapshotBuilder;

final readonly class OptimizeModulesUseCase
{
    public function __construct(
        private ModuleRegistrySnapshotBuilder $builder,
        private ModuleRegistryCacheInterface $cache,
    ) {
    }

    public function execute(): OptimizeModulesResult
    {
        $snapshot = $this->builder->build();
        $cachePath = $this->cache->write($snapshot->loadOrder());

        return new OptimizeModulesResult(
            path: $cachePath,
            count: $snapshot->count(),
        );
    }
}
