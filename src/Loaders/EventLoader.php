<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;

final readonly class EventLoader implements LoaderInterface
{
    public function __construct(
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        $listenersDir = $this->layout->listenersDir($module);

        if (! $this->filesystem->isDirectory($listenersDir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        EventServiceProvider::addEventDiscoveryPaths($listenersDir);

        return LoadReport::applied(['listeners' => [$this->layout->relativeToModule($module, $listenersDir)]]);
    }

    public function priority(): int
    {
        return 35;
    }
}
