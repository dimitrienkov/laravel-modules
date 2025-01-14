<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class MigrationLoaderService
{
    public function __construct(
        protected Application $application,
        protected Filesystem $filesystem,
        protected Repository $configRepository
    ) {}

    public function loadMigrations(): void
    {
        $migrationFiles = $this->getMigrationFiles();

        if ($migrationFiles->isEmpty()) {
            return;
        }

        $migrationFiles->each(
            fn (string $migrationFile) => $this->application->make('migrator')->path($migrationFile)
        );
    }

    private function getMigrationFiles(): Collection
    {
        return (new Collection($this->filesystem->glob($this->getBasePath())))->filter();
    }

    private function getBasePath(): string
    {
        $modulesPath = $this->configRepository->get('modules.paths.modules', 'app/Modules');
        $databasePath = $this->configRepository->get('modules.paths.database', 'Database');
        $migrationsPath = $this->configRepository->get('modules.paths.migrations', 'Migrations');

        return $this->application->basePath("$modulesPath/*/$databasePath/$migrationsPath/*.php");
    }
}
