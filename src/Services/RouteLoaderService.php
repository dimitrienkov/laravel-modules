<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;

final readonly class RouteLoaderService
{
    public function __construct(
        private Application $app,
        private Router      $router,
        private Repository  $config,
        private Filesystem  $filesystem,
    ) {
    }

    public function autoload(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->discoverRoutes()
            ->each(function (array $route): void {
                $this->registerRoute($route);
            });
    }

    /**
     * @return Collection<int, array{type: string, path: string, attributes: array<string, mixed>}>
     */
    private function discoverRoutes(): Collection
    {
        $directories = $this->config->get('modules.paths.directories', []);
        $routesFolder = $this->config->get('modules.paths.routes', 'Routes');
        $routeTypes = $this->getRouteTypes();

        if (! \is_array($directories)) {
            $directories = [];
        }

        if (! \is_string($routesFolder)) {
            $routesFolder = 'Routes';
        }

        /** @var Collection<int, array{type: string, path: string, attributes: array<string, mixed>}> $routes */
        $routes = collect();

        foreach ($directories as $directory) {
            if (! \is_string($directory)) {
                continue;
            }

            $basePath = $this->app->basePath($directory);

            if (! $this->filesystem->isDirectory($basePath)) {
                continue;
            }

            $modules = $this->filesystem->directories($basePath);

            foreach ($modules as $modulePath) {
                foreach ($routeTypes as $type) {
                    $routeFile = "{$modulePath}/{$routesFolder}/{$type}.php";

                    if ($this->filesystem->exists($routeFile)) {
                        $routes->push([
                            'type' => $type,
                            'path' => $routeFile,
                            'attributes' => $this->getRouteAttributes($type),
                        ]);
                    }
                }
            }
        }

        return $routes;
    }

    /**
     * @param array{type: string, path: string, attributes: array<string, mixed>} $route
     */
    private function registerRoute(array $route): void
    {
        $attributes = $route['attributes'];

        $prefix = '';
        if (isset($attributes['prefix']) && \is_string($attributes['prefix'])) {
            $prefix = $attributes['prefix'];
        }

        $middleware = [];
        if (isset($attributes['middleware']) && \is_array($attributes['middleware'])) {
            $middleware = $attributes['middleware'];
        }

        $this->router
            ->prefix($prefix)
            ->middleware($middleware)
            ->group($route['path']);
    }

    /**
     * @return Collection<int, string>
     */
    private function getRouteTypes(): Collection
    {
        $types = $this->config->get('modules.routing.types', []);

        if (! \is_array($types)) {
            return collect();
        }

        return collect(array_keys($types))
            ->filter(fn ($key): bool => \is_string($key))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function getRouteAttributes(string $type): array
    {
        $attributes = $this->config->get("modules.routing.types.{$type}", []);

        if (! \is_array($attributes)) {
            return [];
        }

        /** @var array<string, mixed> */
        return array_filter(
            $attributes,
            fn (mixed $key): bool => \is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }
}
