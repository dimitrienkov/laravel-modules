<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;

final readonly class MigrationLoaderService
{
    public function __construct(
        private Application $app,
        private Repository  $config,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public function autoload(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $paths = $this->discoverMigrations();

        if ($paths->isEmpty()) {
            return;
        }

        $migrator = $this->app->make('migrator');

        /** @phpstan-ignore-next-line callable type narrowed to (mixed, int|string): void */
        $paths->each(fn (mixed $path) => $migrator->path($path));
    }

    /**
     * @return Collection<int, non-falsy-string>
     */
    private function discoverMigrations(): Collection
    {
        /** @var string[] $directories */
        $directories = $this->config->get('modules.paths.directories', []);
        /** @phpstan-ignore-next-line */
        $database = (string)$this->config->get('modules.paths.database', 'Database');
        /** @phpstan-ignore-next-line */
        $migrations = (string)$this->config->get('modules.paths.migrations', 'Migrations');

        return Collection::make($directories)
            ->map(fn (mixed $dir): string => $this->app->basePath("{$dir}/*/{$database}/{$migrations}"))
            ->flatMap(fn (mixed $pattern): array => glob($pattern, GLOB_ONLYDIR) ?: [])
            ->filter()
            ->unique()
            ->values();
    }
}
