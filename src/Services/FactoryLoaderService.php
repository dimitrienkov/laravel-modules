<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

final readonly class FactoryLoaderService
{
    public function __construct(
        protected Repository $configRepository
    ) {
    }

    public function autoload(): void
    {
        /**
         * @var callable(class-string<Model>):class-string<Factory<Model>> $resolver
         */
        $resolver = fn (string $modelName): string => $this->buildFactoryClassNamespace($modelName);

        Factory::guessFactoryNamesUsing($resolver);
    }

    private function buildFactoryClassNamespace(string $modelName): string
    {
        $databasePath = $this->configRepository->get('modules.paths.database', 'Database');
        $factoriesPath = $this->configRepository->get('modules.paths.factories', 'Factories');
        $modelsPath = $this->configRepository->get('modules.paths.models', 'Models');

        if (! \is_string($databasePath) || ! \is_string($factoriesPath) || ! \is_string($modelsPath)) {
            throw new LogicException('Invalid config paths for modules or config directory.');
        }

        $modelClassName = class_basename($modelName);
        $namespace = Str::beforeLast($modelName, "\\$modelsPath\\$modelClassName");

        return "{$namespace}\\{$databasePath}\\{$factoriesPath}\\{$modelClassName}Factory";
    }
}
