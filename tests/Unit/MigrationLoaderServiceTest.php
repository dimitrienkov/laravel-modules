<?php

namespace DimitrienkoV\LaravelModules\Tests\Unit;

use DimitrienkoV\LaravelModules\Services\MigrationLoaderService;
use DimitrienkoV\LaravelModules\Tests\TestCase;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Mockery;

class MigrationLoaderServiceTest extends TestCase
{
    private MigrationLoaderService $migrationLoaderService;
    private Filesystem $mockFilesystem;
    private Application $mockApplication;
    private Migrator $mockMigrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFilesystem = Mockery::mock(Filesystem::class);
        $this->mockApplication = Mockery::mock(Application::class);
        $this->mockMigrator = Mockery::mock(Migrator::class);
        $this->mockConfigRepository = Mockery::mock(Repository::class);

        $this->mockConfigData();

        $this->mockApplication
            ->shouldReceive('make')
            ->with('migrator')
            ->andReturn($this->mockMigrator)
            ->once();

        $this->migrationLoaderService = new MigrationLoaderService(
            $this->mockApplication,
            $this->mockFilesystem,
            $this->mockConfigRepository
        );
    }

    public function testLoadMigrations(): void
    {
        $migrationFiles = ['/app/Modules/Module1/Database/Migrations/2023_01_01_000000_create_users_table.php'];

        $this->mockApplication
            ->shouldReceive('basePath')
            ->with('app/Modules/*/Database/Migrations/*.php')
            ->andReturnUsing(static function (string $arg): string {
                return 'path/to/' . $arg;
            })
            ->once();

        $this->mockFilesystem
            ->shouldReceive('glob')
            ->with('path/to/app/Modules/*/Database/Migrations/*.php')
            ->andReturn($migrationFiles)
            ->once();

        $this->mockMigrator
            ->shouldReceive('path')
            ->with($migrationFiles[0])
            ->once();

        $this->migrationLoaderService->loadMigrations();
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
