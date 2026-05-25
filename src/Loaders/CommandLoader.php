<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\Kernel as FoundationConsoleKernel;

final readonly class CommandLoader implements LoaderInterface
{
    public function __construct(
        private Application $app,
        private ContainerLifecycleHooks $hooks,
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

        $app = $this->app;

        $this->hooks->callAfterResolving(
            ConsoleKernelContract::class,
            static function (object $kernel) use ($app, $commandsDir): void {
                if (! $kernel instanceof FoundationConsoleKernel) {
                    return;
                }

                $app->booted(static fn () => $kernel->addCommandPaths([$commandsDir]));
            },
        );
    }

    public function priority(): int
    {
        return 40;
    }
}
