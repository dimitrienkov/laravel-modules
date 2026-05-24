<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry;

use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;

final readonly class ModuleRegistryCache
{
    private const int CACHE_VERSION = 2;

    private const string CACHE_FILE = 'bootstrap/cache/modules.php';

    public function __construct(
        private ManifestValidatorInterface $validator,
        private ModuleLayout $layout,
        private string $basePath,
    ) {
    }

    public function cachePath(): string
    {
        return $this->basePath . '/' . self::CACHE_FILE;
    }

    public function exists(): bool
    {
        return is_file($this->cachePath());
    }

    /**
     * @param array<int, Module> $loadOrder
     *
     * @return array<string, mixed>
     */
    public function buildPayload(array $loadOrder): array
    {
        $payload = [
            'version' => self::CACHE_VERSION,
            'modules' => [],
            'load_order' => [],
        ];

        foreach ($loadOrder as $module) {
            $payload['modules'][$module->name] = [
                'path' => $module->path,
                'namespace' => $module->namespace,
                'manifest' => $module->toDescriptorArray(),
            ];
            $payload['load_order'][] = $module->name;
        }

        return $payload;
    }

    /**
     * @return array{modules: array<string, Module>, loadOrder: array<int, Module>}
     */
    public function load(): array
    {
        $payload = require $this->cachePath();

        if (! \is_array($payload)) {
            throw InvalidManifestException::forPath($this->cachePath(), 'module cache must return an array.');
        }

        $this->validatePayload($payload);

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
                    "module cache load_order references missing module [{$name}].",
                );
            }

            $loadOrder[] = $modules[$name];
        }

        return [
            'modules' => $modules,
            'loadOrder' => $loadOrder,
        ];
    }

    public function forget(): void
    {
        $path = $this->cachePath();
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param array<mixed> $payload
     */
    private function validatePayload(array $payload): void
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
                    'module cache entries must contain path, namespace and manifest.',
                );
            }
        }

        foreach ($payload['load_order'] as $name) {
            if (! \is_string($name)) {
                throw InvalidManifestException::forPath(
                    $this->cachePath(),
                    'module cache load_order entries must be module names.',
                );
            }
        }
    }
}
