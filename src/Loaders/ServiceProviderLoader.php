<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ClassName;
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
    ) {}

    /**
     * Unlike the convention loaders, an absent Providers directory is not a
     * missing precondition here: registering zero providers is a valid applied
     * outcome with no artifacts, never a skip.
     */
    public function load(Module $module): LoadReport
    {
        $registered = [];

        foreach ($this->providers($module) as $provider) {
            $this->app->register($provider);
            $registered[] = ClassName::short($provider);
        }

        return LoadReport::applied($registered === [] ? [] : ['providers' => $registered]);
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

        $files = $this->filesystem->glob($providersDir . '/*ServiceProvider.php');
        $providers = [];

        foreach ($files as $file) {
            if (! \is_string($file)) {
                continue;
            }

            $class = $module->namespace . '\\Providers\\' . basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            if (! is_subclass_of($class, ServiceProvider::class)) {
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
