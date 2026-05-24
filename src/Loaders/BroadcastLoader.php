<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Filesystem\Filesystem;

final readonly class BroadcastLoader implements LoaderInterface
{
    public function __construct(
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): void
    {
        $channelsFile = $this->layout->channelsFile($module);

        if (! $this->filesystem->exists($channelsFile)) {
            return;
        }

        require $channelsFile;
    }

    public function priority(): int
    {
        return 52;
    }
}
