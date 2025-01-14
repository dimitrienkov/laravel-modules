<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FactoryLoaderService
{
    public function __construct(
        protected Repository $configRepository
    ) {}

    public function configureFactoryNameResolver(): void
    {
        Factory::guessFactoryNamesUsing(fn (string $modelName): ?string => $this->resolveFactoryClass($modelName));
    }

    private function resolveFactoryClass(string $modelName): ?string
    {
        $factoryClass = $this->buildFactoryClassNamespace($modelName);

        return class_exists($factoryClass) ? $factoryClass : null;
    }

    private function buildFactoryClassNamespace(string $modelName): string
    {
        $databasePath = $this->configRepository->get('modules.paths.database', 'Database');
        $factoriesPath = $this->configRepository->get('modules.paths.factories', 'Factories');
        $modelsPath = $this->configRepository->get('modules.paths.models', 'Models');

        $modelClassName = class_basename($modelName);

        $namespace = Str::before($modelName, "\\$modelsPath\\$modelClassName");

        return "$namespace\\$databasePath\\$factoriesPath\\$modelClassName".'Factory';
    }
}
