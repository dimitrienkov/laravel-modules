<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
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

    public function load(Module $module): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $routesDir = $this->layout->routesDir($module);

        if (! $this->filesystem->isDirectory($routesDir)) {
            return;
        }

        foreach ($this->routeFiles($module) as $route) {
            $this->router->group($route['attributes'], $route['path']);
        }
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

        foreach ($this->versionedApiRoutes($module) as $version => $routeFile) {
            $attributes = $this->attributesFor('api');
            $prefixValue = $attributes['prefix'] ?? 'api';
            $prefix = trim(\is_string($prefixValue) ? $prefixValue : 'api', '/');
            $attributes['prefix'] = trim($prefix . '/' . $version, '/');

            $routes[] = [
                'path' => $routeFile,
                'attributes' => $attributes,
            ];
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

    /**
     * @return array<string, string>
     */
    private function versionedApiRoutes(Module $module): array
    {
        $apiDir = $this->layout->routesDir($module) . '/api';

        if (! $this->filesystem->isDirectory($apiDir)) {
            return [];
        }

        $files = $this->filesystem->glob($apiDir . '/*.php') ?: [];
        $routes = [];

        foreach ($files as $file) {
            if (! \is_string($file)) {
                continue;
            }

            $routes[basename($file, '.php')] = $file;
        }

        ksort($routes);

        return $routes;
    }

    private function inertiaAvailable(): bool
    {
        return class_exists('Inertia\\Inertia') || class_exists('Inertia\\ServiceProvider');
    }
}
