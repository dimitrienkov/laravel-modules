<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final readonly class FactoryLoaderService
{
    public function __construct(
        private Repository $config
    ) {
    }

    /**
     * @template TModel of Model
     * @param class-string<TModel> $modelClass
     * @return class-string<Factory<TModel>>
     */
    private function resolveFactoryClass(string $modelClass): string
    {
        /** @phpstan-ignore-next-line */
        $databaseDir = (string)$this->config->get('modules.paths.database', 'Database');

        /** @phpstan-ignore-next-line */
        $factoriesDir = (string)$this->config->get('modules.paths.factories', 'Factories');

        /** @phpstan-ignore-next-line */
        $modelsDir = (string)$this->config->get('modules.paths.models', 'Models');

        $modelName = class_basename($modelClass);
        $moduleNamespace = Str::beforeLast($modelClass, "\\{$modelsDir}\\{$modelName}");

        /** @var class-string<Factory<TModel>> $factoryClass */
        $factoryClass = "{$moduleNamespace}\\{$databaseDir}\\{$factoriesDir}\\{$modelName}Factory";

        return $factoryClass;
    }

    public function autoload(): void
    {
        /** @phpstan-ignore-next-line */
        Factory::guessFactoryNamesUsing(
            fn (string $modelClass): string => $this->resolveFactoryClass($modelClass)
        );
    }
}
