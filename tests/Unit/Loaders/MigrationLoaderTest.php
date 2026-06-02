<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\MigrationLoader;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MigrationLoader::class)]
#[Group('loaders')]
final class MigrationLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('migration-loader');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function registersModuleMigrationPath(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $migrationPath = $modulePath . '/Database/Migrations';
        mkdir($migrationPath, 0755, true);
        $migrator = new Migrator(new NullMigrationRepository(), new NullConnectionResolver(), new Filesystem());
        $app = new Application($this->tempDir);
        $app->singleton('migrator', static fn(): Migrator => $migrator);

        $report = $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertFalse($app->resolved('migrator'));

        $app->make('migrator');

        self::assertContains($migrationPath, $migrator->paths());
        self::assertTrue($report->wasApplied());
        self::assertSame(['migrations' => ['Database/Migrations']], $report->artifacts);
    }

    #[Test]
    public function registersModuleMigrationPathWhenMigratorWasAlreadyResolved(): void
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
    public function returnsEarlyWhenMigrationsDirectoryIsMissing(): void
    {
        $migrator = new Migrator(new NullMigrationRepository(), new NullConnectionResolver(), new Filesystem());
        $app = new Application($this->tempDir);
        $app->singleton('migrator', static fn(): Migrator => $migrator);

        $report = $this->loader($app)
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog'));

        $app->make('migrator');

        self::assertSame([], $migrator->paths());
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::NoDirectory, $report->reason);
    }

    private function loader(Application $app): MigrationLoader
    {
        return new MigrationLoader(new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout());
    }
}

final class NullMigrationRepository implements MigrationRepositoryInterface
{
    public function createRepository(): void {}

    public function delete($migration): void {}

    public function deleteRepository(): void {}

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

    public function log($file, $batch): void {}

    public function repositoryExists(): bool
    {
        return true;
    }

    public function setSource($name): void {}
}

final class NullConnectionResolver implements ConnectionResolverInterface
{
    public function connection($name = null): ConnectionInterface
    {
        throw new RuntimeException('Connection is not needed by MigrationLoaderTest.');
    }

    public function getDefaultConnection(): string
    {
        return 'testing';
    }

    public function setDefaultConnection($name): void {}
}
