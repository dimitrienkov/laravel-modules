<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;

final class ModuleRegistry implements ModuleRegistryInterface
{
    private const int CACHE_VERSION = 2;

    private const string CACHE_FILE = 'bootstrap/cache/modules.php';

    /**
     * @var array<string, Module>|null
     */
    private ?array $modules = null;

    /**
     * @var array<int, Module>|null
     */
    private ?array $orderedModules = null;

    public function __construct(
        private readonly Repository $config,
        private readonly Filesystem $filesystem,
        private readonly ModuleManifestRepositoryInterface $manifests,
        private readonly ManifestValidatorInterface $validator,
        private readonly TopologicalSorter $sorter,
        private readonly ModuleLayout $layout,
        private readonly string $basePath,
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
        $payload = [
            'version' => self::CACHE_VERSION,
            'modules' => [],
            'load_order' => [],
        ];

        foreach ($loaded['loadOrder'] as $module) {
            $payload['modules'][$module->name] = [
                'path' => $module->path,
                'namespace' => $module->namespace,
                'manifest' => $module->toManifestArray(),
            ];
            $payload['load_order'][] = $module->name;
        }

        return $payload;
    }

    public function cachePath(): string
    {
        return $this->basePath . '/' . self::CACHE_FILE;
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

        if ($this->orderedModules === null) {
            throw InvalidManifestException::forPath($this->cachePath(), 'module registry was not loaded.');
        }

        return $this->orderedModules;
    }

    private function ensureLoaded(): void
    {
        if ($this->modules !== null && $this->orderedModules !== null) {
            return;
        }

        $loaded = is_file($this->cachePath())
            ? $this->fromCache()
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

        foreach ($this->moduleDirectories() as $modulePath) {
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

    /**
     * @return array<int, string>
     */
    private function moduleDirectories(): array
    {
        $directories = $this->config->get('modules.paths.directories', []);
        if (! \is_array($directories)) {
            return [];
        }

        $moduleDirectories = [];
        foreach ($directories as $directory) {
            if (! \is_string($directory)) {
                continue;
            }

            $root = $this->basePath . '/' . trim($directory, '/\\');
            if (! $this->filesystem->isDirectory($root)) {
                continue;
            }

            foreach ($this->filesystem->directories($root) as $modulePath) {
                if (is_file($this->layout->manifestFilePath($modulePath))) {
                    $moduleDirectories[] = $modulePath;
                }
            }
        }

        sort($moduleDirectories);

        return $moduleDirectories;
    }

    /**
     * @return array{modules: array<string, Module>, loadOrder: array<int, Module>}
     */
    private function fromCache(): array
    {
        $payload = require $this->cachePath();

        if (! \is_array($payload)) {
            throw InvalidManifestException::forPath($this->cachePath(), 'module cache must return an array.');
        }

        $this->assertCachePayload($payload);

        /** @var array<string, array{path: string, namespace: string, manifest: array<string, mixed>}> $cachedModules */
        $cachedModules = $payload['modules'];
        /** @var array<int, string> $loadOrderNames */
        $loadOrderNames = $payload['load_order'];

        $modules = [];
        foreach ($cachedModules as $name => $cachedModule) {
            $manifestPath = $this->layout->manifestFilePath($cachedModule['path']);
            $this->validator->validate($cachedModule['manifest'], $manifestPath);
            $modules[$name] = Module::fromManifest(
                path: $cachedModule['path'],
                namespace: $cachedModule['namespace'],
                manifest: $cachedModule['manifest'],
                manifestPath: $manifestPath,
            );
        }

        $loadOrder = [];
        foreach ($loadOrderNames as $name) {
            if (! isset($modules[$name])) {
                throw InvalidManifestException::forPath(
                    $this->cachePath(),
                    "module cache load_order references missing module [{$name}]."
                );
            }

            $loadOrder[] = $modules[$name];
        }

        return [
            'modules' => $modules,
            'loadOrder' => $loadOrder,
        ];
    }

    /**
     * @param array<mixed> $payload
     */
    private function assertCachePayload(array $payload): void
    {
        if (($payload['version'] ?? null) !== self::CACHE_VERSION) {
            throw InvalidManifestException::forPath($this->cachePath(), 'module cache version is not supported.');
        }

        if (! isset($payload['modules']) || ! \is_array($payload['modules'])) {
            throw InvalidManifestException::forPath($this->cachePath(), 'module cache modules must be an array.');
        }

        if (! isset($payload['load_order']) || ! \is_array($payload['load_order'])) {
            throw InvalidManifestException::forPath($this->cachePath(), 'module cache load_order must be an array.');
        }

        foreach ($payload['modules'] as $module) {
            if (
                ! \is_array($module)
                || ! \is_string($module['path'] ?? null)
                || ! \is_string($module['namespace'] ?? null)
                || ! \is_array($module['manifest'] ?? null)
            ) {
                throw InvalidManifestException::forPath(
                    $this->cachePath(),
                    'module cache entries must contain path, namespace and manifest.'
                );
            }
        }

        foreach ($payload['load_order'] as $name) {
            if (! \is_string($name)) {
                throw InvalidManifestException::forPath(
                    $this->cachePath(),
                    'module cache load_order entries must be module names.'
                );
            }
        }
    }
}
