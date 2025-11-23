<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

final readonly class ServiceProviderLoaderService
{
    private const string CACHE_FILE = 'bootstrap/cache/modules-providers.php';

    public function __construct(
        private Application $app,
        private Repository  $config,
        private Filesystem  $filesystem,
    ) {
    }

    public function autoload(): void
    {
        $providers = $this->getProviders();

        foreach ($providers as $providerClass) {
            $this->app->register($providerClass);
        }
    }

    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    public function getProviders(): array
    {
        $cachePath = $this->app->basePath(self::CACHE_FILE);

        if (file_exists($cachePath)) {
            return require $cachePath;
        }

        return $this->discoverProviders()->all();
    }

    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    public function discover(): array
    {
        return $this->discoverProviders()->all();
    }

    /**
     * @param array<int, class-string<ServiceProvider>> $data
     */
    public function cache(array $data): void
    {
        $this->filesystem->put(
            $this->app->basePath(self::CACHE_FILE),
            '<?php return ' . var_export($data, true) . ';' . PHP_EOL
        );
    }

    public function clearCache(): bool
    {
        $cachePath = $this->app->basePath(self::CACHE_FILE);

        if (! file_exists($cachePath)) {
            return false;
        }

        return $this->filesystem->delete($cachePath);
    }

    /**
     * @return Collection<int, class-string<ServiceProvider>>
     */
    private function discoverProviders(): Collection
    {
        $basePathPatterns = $this->getBasePathPatterns();
        $providerDir = $this->config->get('modules.paths.providers', 'Providers');

        if (! \is_string($providerDir)) {
            $providerDir = 'Providers';
        }

        $providers = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getClassMap() as $class => $path) {
                $normalizedPath = realpath($path);

                if ($normalizedPath === false) {
                    continue;
                }

                if (! str_contains($normalizedPath, DIRECTORY_SEPARATOR . $providerDir . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                foreach ($basePathPatterns as $pattern) {
                    if (str_contains($normalizedPath, $pattern)) {
                        if (class_exists($class) && is_subclass_of($class, ServiceProvider::class)) {
                            /** @var class-string<ServiceProvider> $class */
                            $providers[] = $class;
                        }

                        break;
                    }
                }
            }
        }

        return Collection::make(array_values(array_unique($providers)));
    }

    /**
     * @return array<int, string>
     */
    private function getBasePathPatterns(): array
    {
        $directories = $this->config->get('modules.paths.directories', []);

        if (! \is_array($directories)) {
            return [];
        }

        /** @var array<int, string> $directories */
        return array_values(array_map(
            fn (string $path): string => $this->app->basePath($path),
            array_filter($directories, 'is_string')
        ));
    }
}
