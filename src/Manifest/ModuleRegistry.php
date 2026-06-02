<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistrySnapshotBuilder;
use DimitrienkoV\LaravelModules\Registry\VO\ModuleRegistrySnapshot;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;

final class ModuleRegistry implements ModuleRegistryInterface
{
    private ?ModuleRegistrySnapshot $snapshot = null;

    public function __construct(
        private readonly ModuleRegistrySnapshotBuilder $builder,
        private readonly ModuleRegistryCacheInterface $cache,
        private readonly ModuleDiagnosticsInterface $diagnostics = new NullModuleDiagnostics(),
    ) {}

    /**
     * @return array<int, Module>
     */
    public function all(): array
    {
        return $this->ensureLoaded()->all();
    }

    public function find(string $name): Module
    {
        return $this->ensureLoaded()->find($name);
    }

    public function has(string $name): bool
    {
        return $this->ensureLoaded()->has($name);
    }

    public function reset(): void
    {
        $this->snapshot = null;
    }

    private function ensureLoaded(): ModuleRegistrySnapshot
    {
        if ($this->snapshot instanceof ModuleRegistrySnapshot) {
            return $this->snapshot;
        }

        if ($this->cache->exists()) {
            $this->snapshot = $this->loadFromCache();
            $this->diagnostics->cacheHit(\count($this->snapshot->all()));

            return $this->snapshot;
        }

        $this->diagnostics->cacheMiss();
        $this->snapshot = $this->builder->build();

        return $this->snapshot;
    }

    private function loadFromCache(): ModuleRegistrySnapshot
    {
        $loaded = $this->cache->load();

        return new ModuleRegistrySnapshot($loaded['loadOrder']);
    }
}
