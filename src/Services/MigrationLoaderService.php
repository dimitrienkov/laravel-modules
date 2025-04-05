<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use LogicException;

final readonly class MigrationLoaderService
{
    public function __construct(
        protected Application $application,
        protected Filesystem  $filesystem,
        protected Repository  $config
    ) {
    }

    public function autoload(): void
    {
        $migrationFiles = $this->getMigrationFiles();

        if ($migrationFiles->isEmpty()) {
            return;
        }

        $migrationFiles->each(
            fn (string $migrationFile) => $this->application->make('migrator')->path($migrationFile)
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function getMigrationFiles(): Collection
    {
        return (new Collection($this->filesystem->glob($this->getBasePath())))->filter();
    }

    private function getBasePath(): string
    {
        $modulesPath = $this->config->get('modules.paths.modules', 'app/Modules');
        $databasePath = $this->config->get('modules.paths.database', 'Database');
        $migrationsPath = $this->config->get('modules.paths.migrations', 'Migrations');

        if (! \is_string($modulesPath) || ! \is_string($databasePath) || ! \is_string($migrationsPath)) {
            throw new LogicException('Invalid config paths for modules.');
        }

        return $this->application->basePath("$modulesPath/*/$databasePath/$migrationsPath/*.php");
    }
}
