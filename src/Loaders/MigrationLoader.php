<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Loaders;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;

final readonly class MigrationLoader implements LoaderInterface
{
    public function __construct(
        private ContainerLifecycleHooks $hooks,
        private Filesystem $filesystem,
        private ModuleLayout $layout,
    ) {}

    public function load(Module $module): LoadReport
    {
        $migrationsDir = $this->layout->migrationsDir($module);

        if (! $this->filesystem->isDirectory($migrationsDir)) {
            return LoadReport::skipped(SkipReason::NoDirectory);
        }

        $this->hooks->callAfterResolving(
            'migrator',
            static function (Migrator $migrator) use ($migrationsDir): void {
                $migrator->path($migrationsDir);
            },
        );

        return LoadReport::applied(['migrations' => [$this->layout->relativeToModule($module, $migrationsDir)]]);
    }

    public function priority(): int
    {
        return 30;
    }
}
