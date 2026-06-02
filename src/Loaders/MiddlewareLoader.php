<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

final readonly class MiddlewareLoader implements LoaderInterface
{
    public function __construct(
        private Router $router,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {}

    public function load(Module $module): LoadReport
    {
        $middlewareDir = $this->layout->middlewareDir($module);

        if (! $this->filesystem->isDirectory($middlewareDir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        $files = $this->filesystem->glob($middlewareDir . '/*.php');
        sort($files);

        $middleware = [];

        foreach ($files as $file) {
            if (! \is_string($file)) {
                continue;
            }

            $this->registerMiddleware($module, $file);
            $middleware[] = basename($file);
        }

        if ($middleware === []) {
            return LoadReport::skipped(SkipReason::EmptyDirectory);
        }

        return LoadReport::applied(['middleware' => $middleware]);
    }

    public function priority(): int
    {
        return 45;
    }

    private function registerMiddleware(Module $module, string $file): void
    {
        $className = basename($file, '.php');
        $fqcn = $this->layout->middlewareNamespace($module) . '\\' . $className;

        if (! class_exists($fqcn)) {
            return;
        }

        $alias = $module->name . '.' . Str::snake($className);
        $this->router->aliasMiddleware($alias, $fqcn);
    }
}
