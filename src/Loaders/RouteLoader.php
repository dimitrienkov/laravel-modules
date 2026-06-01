<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;

final readonly class RouteLoader implements LoaderInterface
{
    public function __construct(
        private Application $app,
        private Router $router,
        private Repository $config,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        if ($this->app->routesAreCached()) {
            return LoadReport::skipped(SkipReason::RoutesCached);
        }

        $routesDir = $this->layout->routesDir($module);

        if (! $this->filesystem->isDirectory($routesDir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        // RouteLoader inspects specific per-type files (not a glob), so it knows
        // whether any applicable route file existed without adding directory I/O.
        // A missing inertia integration only omits the inertia file, never skips.
        $loaded = [];

        foreach ($this->routeFiles($module) as $route) {
            $this->router->group($route['attributes'], $route['path']);
            $loaded[] = basename($route['path']);
        }

        if ($loaded === []) {
            return LoadReport::skipped(SkipReason::EmptyDirectory);
        }

        return LoadReport::applied(['routes' => $loaded]);
    }

    public function priority(): int
    {
        return 50;
    }

    /**
     * @return array<int, array{path: string, attributes: array<string, mixed>}>
     */
    private function routeFiles(Module $module): array
    {
        $routes = [];
        $types = $this->routeTypes();

        foreach (array_keys($types) as $type) {
            if ($type === 'inertia' && ! $this->inertiaAvailable()) {
                continue;
            }

            $routeFile = $this->layout->routeFile($module, $type);
            if ($this->filesystem->exists($routeFile)) {
                $routes[] = [
                    'path' => $routeFile,
                    'attributes' => $this->attributesFor($type),
                ];
            }
        }

        return $routes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function routeTypes(): array
    {
        $types = $this->config->get('modules.routing.types', []);

        if (! \is_array($types)) {
            return [];
        }

        /** @var array<string, array<string, mixed>> */
        return array_filter($types, 'is_array');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFor(string $type): array
    {
        $attributes = $this->config->get("modules.routing.types.{$type}", []);

        if (! \is_array($attributes)) {
            return [];
        }

        $result = [];

        foreach ($attributes as $key => $value) {
            if (\is_string($key) && $value !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function inertiaAvailable(): bool
    {
        return class_exists('Inertia\\Inertia') || class_exists('Inertia\\ServiceProvider');
    }
}
