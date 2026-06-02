<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

final readonly class BladeComponentLoader implements LoaderInterface
{
    public function __construct(
        private ContainerLifecycleHooks $hooks,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        $bladeComponentsDir = $this->layout->bladeComponentsDir($module);

        if (! $this->filesystem->isDirectory($bladeComponentsDir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        $componentNamespace = $this->layout->bladeComponentNamespace($module);

        $this->hooks->callAfterResolving(
            BladeCompiler::class,
            static function (BladeCompiler $blade) use ($module, $componentNamespace): void {
                $blade->componentNamespace($componentNamespace, $module->name);
            },
        );

        return LoadReport::applied(['components' => [$this->layout->relativeToModule($module, $bladeComponentsDir)]]);
    }

    public function priority(): int
    {
        return 34;
    }
}
