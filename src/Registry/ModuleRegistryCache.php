<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry;

use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleCacheException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleCacheWriteException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Registry\VO\ModuleRegistryCachePayload;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Throwable;

final readonly class ModuleRegistryCache
{
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
        $directory = \dirname($cachePath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw ModuleCacheWriteException::forPath($cachePath, "directory [{$directory}] could not be created.");
        }

        $content = '<?php return ' . var_export($payload->toArray(), true) . ';' . PHP_EOL;
        $this->writeAtomically($cachePath, $content);

        return $cachePath;
    }

    public function forget(): void
    {
        $path = $this->cachePath();
        if (! is_file($path)) {
            return;
        }

        if (! unlink($path)) {
            throw ModuleCacheWriteException::forPath($path, 'cache file could not be deleted.');
        }
    }

    private function writeAtomically(string $cachePath, string $contents): void
    {
        $directory = \dirname($cachePath);
        $lock = $this->openLock($cachePath);
        $temporaryPath = null;

        try {
            if (! flock($lock, LOCK_EX)) {
                throw ModuleCacheWriteException::forPath($cachePath, 'exclusive file lock could not be acquired.');
            }

            $temporaryPath = $this->temporaryPath($directory, $cachePath);
            $this->writeTemporaryFile($temporaryPath, $contents, $cachePath);

            if (is_file($cachePath)) {
                $permissions = fileperms($cachePath);
                if ($permissions !== false) {
                    chmod($temporaryPath, $permissions & 0777);
                }
            }

            if (is_dir($cachePath) || ! rename($temporaryPath, $cachePath)) {
                throw ModuleCacheWriteException::forPath(
                    $cachePath,
                    'temporary cache file could not be renamed atomically.',
                );
            }

            $temporaryPath = null;
        } catch (ModuleCacheWriteException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ModuleCacheWriteException::forPath($cachePath, $exception->getMessage(), $exception);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);

            if ($temporaryPath !== null && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * @return resource
     */
    private function openLock(string $cachePath)
    {
        $lock = fopen($cachePath . '.lock', 'c');

        if ($lock === false) {
            throw ModuleCacheWriteException::forPath($cachePath, 'lock file could not be opened.');
        }

        return $lock;
    }

    private function temporaryPath(string $directory, string $cachePath): string
    {
        $temporaryPath = tempnam($directory, basename($cachePath) . '.tmp.');

        if ($temporaryPath === false) {
            throw ModuleCacheWriteException::forPath($cachePath, 'temporary cache file could not be created.');
        }

        return $temporaryPath;
    }

    private function writeTemporaryFile(string $temporaryPath, string $contents, string $cachePath): void
    {
        $handle = fopen($temporaryPath, 'wb');

        if ($handle === false) {
            throw ModuleCacheWriteException::forPath($cachePath, 'temporary cache file could not be opened.');
        }

        try {
            $bytesWritten = fwrite($handle, $contents);
            if ($bytesWritten === false || $bytesWritten !== \strlen($contents)) {
                throw ModuleCacheWriteException::forPath($cachePath, 'temporary cache file write was incomplete.');
            }

            if (! fflush($handle)) {
                throw ModuleCacheWriteException::forPath($cachePath, 'temporary cache file could not be flushed.');
            }
        } finally {
            fclose($handle);
        }
    }
}
