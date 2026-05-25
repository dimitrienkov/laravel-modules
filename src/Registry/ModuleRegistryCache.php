<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry;

use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Exceptions\AtomicWriteException;
use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleCacheException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleCacheWriteException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Registry\VO\ModuleRegistryCachePayload;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Throwable;

final readonly class ModuleRegistryCache
{
    private const string CACHE_FILE = 'bootstrap/cache/modules.php';

    public function __construct(
        private ManifestValidatorInterface $validator,
        private ModuleLayout $layout,
        private string $basePath,
        private AtomicFileWriter $fileWriter = new AtomicFileWriter(),
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
     */
    public function buildPayload(array $loadOrder): ModuleRegistryCachePayload
    {
        return ModuleRegistryCachePayload::fromModules($loadOrder);
    }

    /**
     * @return array{modules: array<string, Module>, loadOrder: array<int, Module>}
     */
    public function load(): array
    {
        $cachePath = $this->cachePath();

        try {
            $raw = require $cachePath;
        } catch (Throwable $exception) {
            throw InvalidModuleCacheException::forPath(
                $cachePath,
                'cache file could not be loaded: ' . $exception->getMessage(),
                $exception,
            );
        }

        if (! \is_array($raw)) {
            throw InvalidModuleCacheException::forPath($cachePath, 'module cache must return an array.');
        }

        $payload = ModuleRegistryCachePayload::fromCachedArray($raw, $cachePath);

        $modules = [];
        foreach ($payload->modules as $name => $descriptor) {
            $manifestPath = $this->layout->manifestFilePath($descriptor->path);
            $this->validator->validate($descriptor->manifest, $manifestPath);
            $modules[$name] = Module::fromManifest(
                path: $descriptor->path,
                namespace: $descriptor->namespace,
                manifest: $descriptor->manifest,
                manifestPath: $manifestPath,
            );
        }

        $loadOrder = [];
        foreach ($payload->loadOrder as $name) {
            $loadOrder[] = $modules[$name];
        }

        return [
            'modules' => $modules,
            'loadOrder' => $loadOrder,
        ];
    }

    /**
     * @param array<int, Module> $loadOrder
     */
    public function write(array $loadOrder): string
    {
        $payload = $this->buildPayload($loadOrder);
        $cachePath = $this->cachePath();
        $content = '<?php return ' . var_export($payload->toArray(), true) . ';' . PHP_EOL;

        try {
            $this->fileWriter->write($cachePath, $content);
        } catch (AtomicWriteException $exception) {
            throw ModuleCacheWriteException::forPath($cachePath, $exception->getMessage(), $exception);
        }

        return $cachePath;
    }

    public function forget(): void
    {
        $path = $this->cachePath();
        if (! is_file($path)) {
            return;
        }

        @unlink($path);

        if (is_file($path)) {
            throw ModuleCacheWriteException::forPath($path, 'cache file could not be deleted.');
        }
    }
}
