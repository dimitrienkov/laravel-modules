<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

final readonly class ObserverLoader implements LoaderInterface
{
    public function __construct(
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): void
    {
        $observersDir = $this->layout->observersDir($module);

        if (! $this->filesystem->isDirectory($observersDir)) {
            return;
        }

        $files = $this->filesystem->glob($observersDir . '/*Observer.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (! \is_string($file)) {
                continue;
            }

            $this->registerObserver($module, $file);
        }
    }

    public function priority(): int
    {
        return 36;
    }

    private function registerObserver(Module $module, string $file): void
    {
        $basename = basename($file, '.php');
        $observerFqcn = $module->namespace . '\\Domain\\Observers\\' . $basename;
        $modelName = substr($basename, 0, -\strlen('Observer'));
        $modelFqcn = $module->namespace . '\\Domain\\Models\\' . $modelName;

        if (! class_exists($observerFqcn) || ! class_exists($modelFqcn)) {
            return;
        }

        if ((new ReflectionClass($observerFqcn))->isAbstract()) {
            return;
        }

        if (! is_subclass_of($modelFqcn, Model::class)) {
            return;
        }

        $modelFqcn::observe($observerFqcn);
    }
}
