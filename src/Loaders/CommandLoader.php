<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use ReflectionClass;

final readonly class CommandLoader implements LoaderInterface
{
    public function __construct(
        private Application $app,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $commandsDir = $this->layout->commandsDir($module);

        if (! $this->filesystem->isDirectory($commandsDir)) {
            return;
        }

        $commands = $this->discoverCommands($module, $commandsDir);

        if ($commands === []) {
            return;
        }

        $this->app->afterResolving(
            ConsoleKernel::class,
            static function (object $kernel) use ($commands): void {
                if (! $kernel instanceof ConsoleKernel) {
                    return;
                }

                $kernel->addCommands($commands);
            },
        );
    }

    public function priority(): int
    {
        return 40;
    }

    /**
     * @return list<class-string<Command>>
     */
    private function discoverCommands(Module $module, string $commandsDir): array
    {
        $files = $this->filesystem->glob($commandsDir . '/*.php') ?: [];
        sort($files);

        $commands = [];

        foreach ($files as $file) {
            if (! \is_string($file)) {
                continue;
            }

            $class = $module->namespace . '\\Console\\Commands\\' . basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, Command::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            /** @var class-string<Command> $class */
            $commands[] = $class;
        }

        return $commands;
    }
}
