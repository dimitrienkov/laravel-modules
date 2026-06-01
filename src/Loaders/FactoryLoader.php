<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use ReflectionException;
use ReflectionProperty;
use Throwable;

final class FactoryLoader implements LoaderInterface
{
    /**
     * @var array<string, string>
     */
    private array $factoryNamespacesByModelNamespace = [];

    private mixed $previousFactoryNameResolver = null;

    private bool $registered = false;

    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $filesystem,
        private readonly ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): void
    {
        $factoriesDir = $this->layout->factoriesDir($module);

        if (! $this->filesystem->isDirectory($factoriesDir)) {
            return;
        }

        $this->factoryNamespacesByModelNamespace[$this->layout->modelNamespace($module) . '\\'] =
            $this->layout->factoryNamespace($module) . '\\';

        if ($this->registered) {
            return;
        }

        $this->previousFactoryNameResolver = $this->currentFactoryNameResolver();
        Factory::guessFactoryNamesUsing($this->factoryClassForModel(...));
        $this->registered = true;
    }

    public function priority(): int
    {
        return 31;
    }

    public function factoryClassFor(string $modelClass): string
    {
        foreach ($this->factoryNamespacesByModelNamespace as $modelNamespace => $factoryNamespace) {
            if (! str_starts_with($modelClass, $modelNamespace)) {
                continue;
            }

            $modelBaseName = basename(str_replace('\\', '/', $modelClass));
            /** @var class-string<Factory<Model>> $factoryClass */
            $factoryClass = $factoryNamespace . $modelBaseName . 'Factory';

            return $factoryClass;
        }

        if (\is_callable($this->previousFactoryNameResolver)) {
            $resolved = ($this->previousFactoryNameResolver)($modelClass);

            if (\is_string($resolved)) {
                return $resolved;
            }
        }

        return $this->defaultFactoryClassFor($modelClass);
    }

    /**
     * @param class-string<Model> $modelClass
     *
     * @return class-string<Factory<Model>>
     */
    private function factoryClassForModel(string $modelClass): string
    {
        /** @var class-string<Factory<Model>> $factoryClass */
        $factoryClass = $this->factoryClassFor($modelClass);

        return $factoryClass;
    }

    private function currentFactoryNameResolver(): mixed
    {
        try {
            $property = new ReflectionProperty(Factory::class, 'factoryNameResolver');

            return $property->getValue();
        } catch (ReflectionException) {
            return null;
        }
    }

    private function defaultFactoryClassFor(string $modelClass): string
    {
        $appNamespace = $this->applicationNamespace();
        $modelsNamespace = $appNamespace . 'Models\\';

        if (str_starts_with($modelClass, $modelsNamespace)) {
            $modelName = substr($modelClass, \strlen($modelsNamespace));
        } elseif (str_starts_with($modelClass, $appNamespace)) {
            $modelName = substr($modelClass, \strlen($appNamespace));
        } else {
            $modelName = $modelClass;
        }

        return Factory::$namespace . $modelName . 'Factory';
    }

    private function applicationNamespace(): string
    {
        try {
            if (! is_file($this->app->basePath('composer.json'))) {
                return 'App\\';
            }

            return $this->app->getNamespace();
        } catch (Throwable) {
            return 'App\\';
        }
    }
}
