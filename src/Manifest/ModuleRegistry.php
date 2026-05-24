<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;

final class ModuleRegistry implements ModuleRegistryInterface
{
    /**
     * @var array<string, Module>|null
     */
    private ?array $modules = null;

    /**
     * @var array<int, Module>|null
     */
    private ?array $orderedModules = null;

    public function __construct(
        private readonly ModuleManifestRepositoryInterface $manifests,
        private readonly TopologicalSorter $sorter,
        private readonly ModuleDirectoryScanner $scanner,
        private readonly ModuleRegistryCache $cache,
    ) {
    }

    /**
     * @return array<int, Module>
     */
    public function all(): array
    {
        $this->ensureLoaded();

        /** @var array<string, Module> $modules */
        $modules = $this->modules;

        return array_values($modules);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCachePayload(): array
    {
        $loaded = $this->scan();

        return $this->cache->buildPayload($loaded['loadOrder']);
    }

    public function cachePath(): string
    {
        return $this->cache->cachePath();
    }

    public function find(string $name): Module
    {
        $this->ensureLoaded();

        /** @var array<string, Module> $modules */
        $modules = $this->modules;

        return $modules[$name] ?? throw ModuleNotFoundException::forName($name);
    }

    /**
     * @return array<int, Module>
     */
    public function loadOrder(): array
    {
        $this->ensureLoaded();

        /** @var array<int, Module> $orderedModules */
        $orderedModules = $this->orderedModules;

        return $orderedModules;
    }

    public function reset(): void
    {
        $this->modules = null;
        $this->orderedModules = null;
    }

    private function ensureLoaded(): void
    {
        if ($this->modules !== null && $this->orderedModules !== null) {
            return;
        }

        $loaded = $this->cache->exists()
            ? $this->cache->load()
            : $this->scan();

        $this->modules = $loaded['modules'];
        $this->orderedModules = $loaded['loadOrder'];
    }

    /**
     * @return array{modules: array<string, Module>, loadOrder: array<int, Module>}
     */
    private function scan(): array
    {
        $modules = [];

        foreach ($this->scanner->scan() as $modulePath) {
            $modules[] = $this->manifests->load($modulePath);
        }

        $loadOrder = $this->sorter->sort($modules);
        $moduleMap = [];

        foreach ($loadOrder as $module) {
            $moduleMap[$module->name] = $module;
        }

        return [
            'modules' => $moduleMap,
            'loadOrder' => $loadOrder,
        ];
    }
}
