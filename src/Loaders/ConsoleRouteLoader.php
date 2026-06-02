<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\Kernel as FoundationConsoleKernel;

final readonly class ConsoleRouteLoader implements LoaderInterface
{
    public function __construct(
        private Application $app,
        private ContainerLifecycleHooks $hooks,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {}

    public function load(Module $module): LoadReport
    {
        if (! $this->app->runningInConsole()) {
            return LoadReport::skipped(SkipReason::NotRunningInConsole);
        }

        $consoleRoutesFile = $this->layout->consoleRoutesFile($module);

        if (! $this->filesystem->exists($consoleRoutesFile)) {
            return LoadReport::skipped(SkipReason::FileNotFound);
        }

        $app = $this->app;

        $this->hooks->callAfterResolving(
            ConsoleKernelContract::class,
            static function (object $kernel) use ($app, $consoleRoutesFile): void {
                if (! $kernel instanceof FoundationConsoleKernel) {
                    return;
                }

                $app->booted(static fn() => $kernel->addCommandRoutePaths([$consoleRoutesFile]));
            },
        );

        return LoadReport::applied(['console' => [basename($consoleRoutesFile)]]);
    }

    public function priority(): int
    {
        return 51;
    }
}
