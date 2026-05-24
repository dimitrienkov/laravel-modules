<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;

final readonly class MigrationLoader implements LoaderInterface
{
    public function __construct(
        private Migrator $migrator,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {
    }

    public function load(Module $module): void
    {
        $migrationsDir = $this->layout->migrationsDir($module);

        if (! $this->filesystem->isDirectory($migrationsDir)) {
            return;
        }

        $this->migrator->path($migrationsDir);
    }

    public function priority(): int
    {
        return 30;
    }
}
