<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;

final class FactoryLoader implements LoaderInterface
{
    /**
     * @var array<string, string>
     */
    private array $factoryNamespacesByModelNamespace = [];

    private bool $registered = false;

    public function __construct(
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

        $this->factoryNamespacesByModelNamespace[$module->namespace . '\\Domain\\Models\\'] =
            $module->namespace . '\\Database\\Factories\\';

        if ($this->registered) {
            return;
        }

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

        $modelBaseName = basename(str_replace('\\', '/', $modelClass));

        return 'Database\\Factories\\' . $modelBaseName . 'Factory';
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
}
