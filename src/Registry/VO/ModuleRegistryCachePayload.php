<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleCacheException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class ModuleRegistryCachePayload
{
    private const int SUPPORTED_VERSION = 4;

    /**
     * @param array<string, CachedModuleDescriptor> $modules
     * @param array<int, string>                    $loadOrder
     */
    public function __construct(
        public int $version,
        public array $modules,
        public array $loadOrder,
    ) {
    }

    /**
     * @param array<int, Module> $loadOrder
     */
    public static function fromModules(array $loadOrder): self
    {
        $modules = [];
        $names = [];

        foreach ($loadOrder as $module) {
            $modules[$module->name] = new CachedModuleDescriptor(
                name: $module->name,
                path: $module->path,
                namespace: $module->namespace,
                manifest: $module->toDescriptorArray(),
            );
            $names[] = $module->name;
        }

        return new self(
            version: self::SUPPORTED_VERSION,
            modules: $modules,
            loadOrder: $names,
        );
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromCachedArray(array $raw, string $cachePath): self
    {
        if (($raw['version'] ?? null) !== self::SUPPORTED_VERSION) {
            throw InvalidModuleCacheException::forPath($cachePath, 'module cache version is not supported.');
        }

        if (! isset($raw['modules']) || ! \is_array($raw['modules'])) {
            throw InvalidModuleCacheException::forPath($cachePath, 'module cache modules must be an array.');
        }

        if (! isset($raw['load_order']) || ! \is_array($raw['load_order'])) {
            throw InvalidModuleCacheException::forPath($cachePath, 'module cache load_order must be an array.');
        }

        $modules = [];
        foreach ($raw['modules'] as $name => $entry) {
            if (! \is_string($name)) {
                throw InvalidModuleCacheException::forPath($cachePath, 'module cache module keys must be strings.');
            }

            if (! \is_array($entry)) {
                throw InvalidModuleCacheException::forPath(
                    $cachePath,
                    "module cache entry [{$name}] must be an array.",
                );
            }

            $modules[$name] = CachedModuleDescriptor::fromCacheEntry($name, $entry, $cachePath);
        }

        $loadOrder = [];
        $seen = [];
        foreach ($raw['load_order'] as $name) {
            if (! \is_string($name)) {
                throw InvalidModuleCacheException::forPath(
                    $cachePath,
                    'module cache load_order entries must be module names.',
                );
            }

            if (isset($seen[$name])) {
                throw InvalidModuleCacheException::forPath(
                    $cachePath,
                    "module cache load_order contains duplicate name [{$name}].",
                );
            }

            if (! isset($modules[$name])) {
                throw InvalidModuleCacheException::forPath(
                    $cachePath,
                    "module cache load_order references missing module [{$name}].",
                );
            }

            $seen[$name] = true;
            $loadOrder[] = $name;
        }

        foreach (array_keys($modules) as $name) {
            if (! isset($seen[$name])) {
                throw InvalidModuleCacheException::forPath(
                    $cachePath,
                    "module cache contains module [{$name}] absent from load_order.",
                );
            }
        }

        return new self(
            version: self::SUPPORTED_VERSION,
            modules: $modules,
            loadOrder: $loadOrder,
        );
    }

    /**
     * @return array{version: int, modules: array<string, array{path: string, namespace: string, manifest: array<string, mixed>}>, load_order: array<int, string>}
     */
    public function toArray(): array
    {
        $modules = [];
        foreach ($this->modules as $name => $descriptor) {
            $modules[$name] = $descriptor->toArray();
        }

        return [
            'version' => $this->version,
            'modules' => $modules,
            'load_order' => $this->loadOrder,
        ];
    }
}
