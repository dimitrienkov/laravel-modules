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

        $migrationPaths = $migrationFiles->map(fn (string $file): string => dirname($file))
            ->unique()
            ->values();

        $migrator = $this->application->make('migrator');
        $migrationPaths->each(
            fn (string $path) => $migrator->path($path)
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function getMigrationFiles(): Collection
    {
        $basePaths = collect([
            $this->config->get('modules.paths.modules'),
            $this->config->get('modules.paths.integrations'),
            $this->config->get('modules.paths.subsystems'),
        ])->filter();

        $databasePath = $this->config->get('modules.paths.database', 'Database');
        $migrationsPath = $this->config->get('modules.paths.migrations', 'Migrations');

        if (! \is_string($databasePath) || ! \is_string($migrationsPath)) {
            throw new LogicException('Invalid config paths for database or migrations.');
        }

        $allFiles = collect();

        foreach ($basePaths as $basePath) {
            $pattern = $this->application->basePath("{$basePath}/*/{$databasePath}/{$migrationsPath}/*.php");
            $files = $this->filesystem->glob($pattern);

            if ($files !== false) {
                $allFiles = $allFiles->merge($files);
            }
        }

        return $allFiles->filter();
    }
}
