<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

final readonly class ConsoleRouteLoader implements LoaderInterface
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

        $consoleRoutesFile = $this->layout->consoleRoutesFile($module);

        if (! $this->filesystem->exists($consoleRoutesFile)) {
            return;
        }

        $this->app->afterResolving(
            ConsoleKernel::class,
            static function (object $kernel) use ($consoleRoutesFile): void {
                if (! $kernel instanceof ConsoleKernel) {
                    return;
                }

                $kernel->addCommandRoutePaths([$consoleRoutesFile]);
            },
        );
    }

    public function priority(): int
    {
        return 51;
    }
}
