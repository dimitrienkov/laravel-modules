<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Throwable;

final readonly class RouteLoaderService
{
    public function __construct(
        private Application    $app,
        private RouteRegistrar $router,
        private Repository     $config,
    ) {
    }

    /**
     * @param array<string>|null $types
     * @throws Throwable
     */
    public function autoload(?array $types = null): void
    {
        $types = $types ?? $this->getRoutingTypesFromConfig();
        $moduleRoutes = $this->discoverRoutes($types);
        $moduleRoutes->each(fn (string $routeFile) => $this->registerModuleRoutes($routeFile));
    }

    /**
     * @param array<string> $types
     * @return Collection<int, string>
     */
    private function discoverRoutes(array $types): Collection
    {
        $modulesPath = $this->getModulesPath();
        $moduleDirectories = $this->getModuleDirectories($modulesPath);

        /** @var Collection<int, string> $routes */
        $routes = Collection::make($types)
            ->map(fn (string $type) => $this->discoverRoutesByType($moduleDirectories, $type))
            ->flatten()
            ->filter(fn ($route) => \is_string($route))
            ->values();

        return $routes;
    }

    /**
     * @param string $modulesPath
     * @return Collection<int, string>
     */
    private function getModuleDirectories(string $modulesPath): Collection
    {
        return Collection::make(File::directories($modulesPath));
    }

    /**
     * @param Collection<int, string> $moduleDirectories
     * @param string $type
     * @return array<string>
     */
    private function discoverRoutesByType(Collection $moduleDirectories, string $type): array
    {
        return $moduleDirectories
            ->map(fn (string $modulePath): ?string => $this->findRouteFile($modulePath, $type))
            ->filter()
            ->all();
    }

    private function findRouteFile(string $modulePath, string $type): ?string
    {
        $routePath = implode(DIRECTORY_SEPARATOR, [
            $modulePath,
            'Routes',
            "{$type}.php",
        ]);

        return is_file($routePath) ? $routePath : null;
    }

    private function registerModuleRoutes(string $routeFile): void
    {
        $attributes = $this->getAttributesFromConfig($routeFile);

        $prefix = isset($attributes['prefix']) && \is_string($attributes['prefix'])
            ? $attributes['prefix']
            : '';

        $middleware = $attributes['middleware'] ?? [];

        if (! \is_array($middleware) && ! \is_string($middleware)) {
            $middleware = [];
        }

        $this->router
            ->prefix($prefix)
            ->middleware($middleware)
            ->group($routeFile);
    }

    private function getModulesPath(): string
    {
        /** @var string $modulesPath */
        $modulesPath = $this->config->get('modules.paths.modules', 'app/Modules');

        return $this->app->basePath($modulesPath);
    }

    /**
     * @return array<string>
     */
    private function getRoutingTypesFromConfig(): array
    {
        /** @var array<string, mixed> $routingTypes */
        $routingTypes = $this->config->get('modules.routing.types', []);

        if (empty($routingTypes) || ! \is_array($routingTypes)) {
            return ['web', 'api'];
        }

        return array_keys($routingTypes);
    }

    /**
     * @return array<string, mixed>
     */
    private function getAttributesFromConfig(string $routeFile): array
    {
        $routeType = basename($routeFile, '.php');
        /** @var array<string, mixed> $attributes */
        $attributes = (array)$this->config->get("modules.routing.types.{$routeType}", []);

        return $attributes;
    }
}
