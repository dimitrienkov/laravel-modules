<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Throwable;

final readonly class ServiceProviderLoaderService
{
    public function __construct(
        private Application $app,
        private Repository $config,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function autoload(): void
    {
        $serviceProviders = $this->discoverServiceProviders();

        $serviceProviders->each(function (string $serviceProviderClass): void {
            $this->registerServiceProvider($serviceProviderClass);
        });
    }

    /**
     * @return Collection<int, string>
     */
    private function discoverServiceProviders(): Collection
    {
        $basePaths = $this->getBasePaths();
        $allDirectories = $basePaths
            ->map(fn (string $basePath): Collection => $this->getDirectories($basePath))
            ->flatten();

        return $allDirectories
            ->map(fn (string $directoryPath): array => $this->discoverServiceProvidersInDirectory($directoryPath))
            ->flatten()
            ->filter(fn ($provider): bool => \is_string($provider) && $this->isServiceProvider($provider))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function getBasePaths(): Collection
    {
        $paths = [];
        
        $modulesPath = $this->config->get('modules.paths.modules');
        if ($modulesPath && \is_string($modulesPath)) {
            $paths[] = $this->app->basePath($modulesPath);
        }
        
        $integrationsPath = $this->config->get('modules.paths.integrations');
        if ($integrationsPath && \is_string($integrationsPath)) {
            $paths[] = $this->app->basePath($integrationsPath);
        }
        
        $subsystemsPath = $this->config->get('modules.paths.subsystems');
        if ($subsystemsPath && \is_string($subsystemsPath)) {
            $paths[] = $this->app->basePath($subsystemsPath);
        }

        return Collection::make($paths)->filter(fn (string $path): bool => is_dir($path));
    }

    /**
     * @param string $basePath
     * @return Collection<int, string>
     */
    private function getDirectories(string $basePath): Collection
    {
        return Collection::make(File::directories($basePath));
    }

    /**
     * @param string $directoryPath
     * @return array<string>
     */
    private function discoverServiceProvidersInDirectory(string $directoryPath): array
    {
        $providersPath = $this->getProvidersPath($directoryPath);

        if (! is_dir($providersPath)) {
            return [];
        }

        $providerFiles = File::allFiles($providersPath);

        return Collection::make($providerFiles)
            ->map(fn ($file): ?string => $this->getClassNameFromFile($file->getPathname()))
            ->filter()
            ->all();
    }

    private function getProvidersPath(string $directoryPath): string
    {
        $providersPath = $this->config->get('modules.paths.providers', 'Providers');

        if (! \is_string($providersPath)) {
            throw new LogicException('Invalid config path for providers directory.');
        }

        return implode(DIRECTORY_SEPARATOR, [$directoryPath, $providersPath]);
    }

    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        if (! preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            return null;
        }

        if (! preg_match('/(?:final\s+)?class\s+(\w+)(?:\s+extends\s+(\w+))?/', $content, $classMatches)) {
            return null;
        }

        $namespace = trim($namespaceMatches[1]);
        $className = trim($classMatches[1]);

        return $namespace . '\\' . $className;
    }

    private function isServiceProvider(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        return is_subclass_of($className, ServiceProvider::class);
    }

    private function registerServiceProvider(string $serviceProviderClass): void
    {
        $this->app->register($serviceProviderClass);
    }

}
