<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;

final readonly class ViewLoader implements LoaderInterface
{
    public function __construct(
        private ContainerLifecycleHooks $hooks,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        $viewsDir = $this->layout->viewsDir($module);

        if (! $this->filesystem->isDirectory($viewsDir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        $this->hooks->callAfterResolving(
            'view',
            static function (ViewFactory $view) use ($module, $viewsDir): void {
                $view->addNamespace($module->name, $viewsDir);
            },
        );

        return LoadReport::applied(['views' => [$this->layout->relativeToModule($module, $viewsDir)]]);
    }

    public function priority(): int
    {
        return 33;
    }
}
