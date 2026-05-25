<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\MigrationLoader;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MigrationLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-migration-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_registers_module_migration_path(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $migrationPath = $modulePath . '/Database/Migrations';
        mkdir($migrationPath, 0755, true);
        $migrator = new Migrator(new NullMigrationRepository(), new NullConnectionResolver(), new Filesystem());
        $app = new Application($this->tempDir);
        $app->singleton('migrator', static fn (): Migrator => $migrator);

        $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertFalse($app->resolved('migrator'));

        $app->make('migrator');

        self::assertContains($migrationPath, $migrator->paths());
    }

    #[Test]
    public function it_registers_module_migration_path_when_migrator_was_already_resolved(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $migrationPath = $modulePath . '/Database/Migrations';
        mkdir($migrationPath, 0755, true);
        $migrator = new Migrator(new NullMigrationRepository(), new NullConnectionResolver(), new Filesystem());
        $app = new Application($this->tempDir);
        $app->instance('migrator', $migrator);
        $app->make('migrator');

        $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertContains($migrationPath, $migrator->paths());
    }

    #[Test]
    public function it_returns_early_when_migrations_directory_is_missing(): void
    {
        $migrator = new Migrator(new NullMigrationRepository(), new NullConnectionResolver(), new Filesystem());
        $app = new Application($this->tempDir);
        $app->singleton('migrator', static fn (): Migrator => $migrator);

        $this->loader($app)
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog'));

        $app->make('migrator');

        self::assertSame([], $migrator->paths());
    }

    private function loader(Application $app): MigrationLoader
    {
        return new MigrationLoader(new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout());
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}

final class NullMigrationRepository implements MigrationRepositoryInterface
{
    public function createRepository(): void
    {
    }

    public function delete($migration): void
    {
    }

    public function deleteRepository(): void
    {
    }

    public function getLast(): array
    {
        return [];
    }

    public function getMigrationBatches(): array
    {
        return [];
    }

    public function getMigrations($steps): array
    {
        return [];
    }

    public function getMigrationsByBatch($batch): array
    {
        return [];
    }

    public function getNextBatchNumber(): int
    {
        return 1;
    }

    public function getRan(): array
    {
        return [];
    }

    public function log($file, $batch): void
    {
    }

    public function repositoryExists(): bool
    {
        return true;
    }

    public function setSource($name): void
    {
    }
}

final class NullConnectionResolver implements ConnectionResolverInterface
{
    public function connection($name = null): ConnectionInterface
    {
        throw new \RuntimeException('Connection is not needed by MigrationLoaderTest.');
    }

    public function getDefaultConnection(): string
    {
        return 'testing';
    }

    public function setDefaultConnection($name): void
    {
    }
}
