<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

final readonly class ServiceProviderLoader implements LoaderInterface
{
    public function __construct(
        private Application $app,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): void
    {
        foreach ($this->providers($module) as $provider) {
            $this->app->register($provider);
        }
    }

    public function priority(): int
    {
        return 20;
    }

    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    private function providers(Module $module): array
    {
        $providersDir = $this->layout->providersDir($module);

        if (! $this->filesystem->isDirectory($providersDir)) {
            return [];
        }

        $files = $this->filesystem->glob($providersDir . '/*ServiceProvider.php') ?: [];
        $providers = [];

        foreach ($files as $file) {
            if (! \is_string($file)) {
                continue;
            }

            $class = $module->namespace . '\\Providers\\' . basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, ServiceProvider::class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $providers[] = $class;
        }

        sort($providers);

        return $providers;
    }
}
