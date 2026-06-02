<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Boots a temporary `app/` root whose `Modules/` tree the native module-aware
 * generators write into, backed by an in-memory {@see FakeModuleRegistry}.
 *
 * The generators resolve their destination through the inherited
 * `GeneratorCommand::getPath()`, which is anchored on `app_path()`; pointing the
 * app path at a temp dir keeps every generated artifact contained and disposable.
 *
 * @property \Illuminate\Foundation\Application $app
 */
trait InteractsWithModuleGenerators
{
    protected string $generatorTempDir;

    protected FakeModuleRegistry $moduleRegistry;

    protected function bootModuleGeneratorEnvironment(): void
    {
        $this->generatorTempDir = sys_get_temp_dir() . '/maw_gen_' . bin2hex(random_bytes(6));
        mkdir($this->generatorTempDir . '/app/Modules', 0755, true);

        // GeneratorCommand::rootNamespace() resolves the app namespace by matching
        // realpath(app_path()) against the PSR-4 map in <base>/composer.json. Point
        // the base path at the temp dir and ship a matching composer.json so `App\`
        // resolves and every generated file is contained under the temp app root.
        file_put_contents(
            $this->generatorTempDir . '/composer.json',
            (string) json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]], JSON_PRETTY_PRINT),
        );

        $this->app->setBasePath($this->generatorTempDir);

        $this->moduleRegistry = new FakeModuleRegistry();
        $this->app->instance(ModuleRegistryInterface::class, $this->moduleRegistry);
    }

    protected function cleanModuleGeneratorEnvironment(): void
    {
        (new Filesystem())->deleteDirectory($this->generatorTempDir);
    }

    protected function registerModuleForGenerators(string $name = 'blog'): Module
    {
        $studly = Str::studly($name);

        $module = ModuleFactory::make(
            name: $name,
            path: $this->generatorTempDir . '/app/Modules/' . $studly,
            namespace: 'App\\Modules\\' . $studly,
        );

        $this->moduleRegistry->add($module);

        return $module;
    }

    /**
     * @param class-string $commandClass
     */
    protected function registerGeneratorCommand(string $commandClass): void
    {
        $this->app->make(Kernel::class)->registerCommand($this->app->make($commandClass));
    }

    /**
     * Architectural generators (make:use-case, make:vo, …) take an injected stub
     * path on top of the Filesystem, so they're constructed explicitly here with
     * the package's real stubs directory.
     *
     * @param class-string<\Illuminate\Console\GeneratorCommand> $commandClass
     */
    protected function registerArchitecturalGeneratorCommand(string $commandClass): void
    {
        $stubsPath = \dirname(__DIR__, 2) . '/stubs';

        $this->app->make(Kernel::class)->registerCommand(
            new $commandClass(new Filesystem(), $stubsPath),
        );
    }

    protected function appPath(string $relative = ''): string
    {
        return rtrim($this->generatorTempDir . '/app/' . ltrim($relative, '/'), '/');
    }

    protected function modulePath(string $relative = '', string $name = 'blog'): string
    {
        $base = $this->generatorTempDir . '/app/Modules/' . Str::studly($name);

        return $relative === '' ? $base : $base . '/' . ltrim($relative, '/');
    }
}
