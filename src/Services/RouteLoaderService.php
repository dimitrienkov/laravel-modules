<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use DimitrienkoV\LaravelModules\DTOs\RouteGroupConfigDTO;
use DimitrienkoV\LaravelModules\Enums\RouteTypeEnum;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Collection;

class RouteLoaderService
{
    public function __construct(
        protected Application $application,
        protected Filesystem $filesystem,
        protected RouteRegistrar $routeRegistrar,
        protected Repository $configRepository
    ) {}

    public function loadRoutes(RouteTypeEnum $type = RouteTypeEnum::ALL): void
    {
        $typesToLoad = $type === RouteTypeEnum::ALL ? RouteTypeEnum::getAvailableTypes() : [$type];

        foreach ($typesToLoad as $typeEnum) {
            $this->loadRoutesByType($typeEnum);
        }
    }

    private function loadRoutesByType(RouteTypeEnum $typeEnum): void
    {
        $routeFiles = $this->getRouteFilesForType($typeEnum);

        if ($routeFiles->isEmpty()) {
            return;
        }

        $routeFiles->each(
            fn (string $routeFile) => $this->registerRouteGroup($routeFile, $this->defaultRouteConfig($typeEnum))
        );
    }

    private function getRouteFilesForType(RouteTypeEnum $typeEnum): Collection
    {
        return (new Collection($this->filesystem->glob($this->getBasePath($typeEnum))))->filter();
    }

    private function getBasePath(RouteTypeEnum $typeEnum): string
    {
        $modulesPath = $this->configRepository->get('modules.paths.modules', 'app/Modules');
        $routesPath = $this->configRepository->get('modules.paths.routes', 'Routes');

        return $this->application->basePath("$modulesPath/*/$routesPath/{$typeEnum->value}.php");
    }

    private function registerRouteGroup(string $routeFile, RouteGroupConfigDTO $configDTO): void
    {
        $this->routeRegistrar->prefix($configDTO->prefix)->middleware($configDTO->middleware)->group($routeFile);
    }

    private function defaultRouteConfig(RouteTypeEnum $typeEnum): RouteGroupConfigDTO
    {
        $routeDefaults = $this->configRepository->get('modules.route.middlewares');

        $defaultConfig = $routeDefaults[$typeEnum->value] ?? [];

        return new RouteGroupConfigDTO(
            $defaultConfig['prefix'] ?? '',
            $defaultConfig['middleware'] ?? ''
        );
    }
}
