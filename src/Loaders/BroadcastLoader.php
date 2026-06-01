<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Filesystem\Filesystem;

final readonly class BroadcastLoader implements LoaderInterface
{
    public function __construct(
        private ContainerLifecycleHooks $hooks,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        $channelsFile = $this->layout->channelsFile($module);

        if (! $this->filesystem->exists($channelsFile)) {
            return LoadReport::skipped(SkipReason::FileNotFound);
        }

        $this->hooks->callAfterResolving(
            BroadcastManager::class,
            static function () use ($channelsFile): void {
                require $channelsFile;
            },
        );

        return LoadReport::applied(['channels' => [basename($channelsFile)]]);
    }

    public function priority(): int
    {
        return 52;
    }
}
