<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

final readonly class BladeComponentLoader implements LoaderInterface
{
    public function __construct(
        private BladeCompiler $blade,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): void
    {
        $bladeComponentsDir = $this->layout->bladeComponentsDir($module);

        if (! $this->filesystem->isDirectory($bladeComponentsDir)) {
            return;
        }

        $this->blade->componentNamespace(
            $module->namespace . '\\View\\Components',
            $module->name,
        );
    }

    public function priority(): int
    {
        return 34;
    }
}
