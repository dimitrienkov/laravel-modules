<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesRemoveCommand;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesRemoveCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/remove_cmd_' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
        mkdir($this->tempDir . '/backups', 0755, true);

        $this->registerServices();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function removeWithConfirmation(): void
    {
        $this->installModule('blog');

        $this->artisan('modules:remove', ['name' => 'blog', '--yes' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('removed');
    }

    #[Test]
    public function removeFailsWhenModuleNotFound(): void
    {
        $this->artisan('modules:remove', ['name' => 'nonexistent', '--yes' => true])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    #[Test]
    public function removeShowsBackupPath(): void
    {
        $this->installModule('blog');

        $this->artisan('modules:remove', ['name' => 'blog', '--yes' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Backup');
    }

    #[Test]
    public function permanentRemoveShowsNoBackup(): void
    {
        $this->installModule('blog');

        $this->artisan('modules:remove', ['name' => 'blog', '--yes' => true, '--delete-permanently' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('permanently deleted');
    }

    private function registerServices(): void
    {
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        $this->app->instance(ModuleRegistryInterface::class, $registry);
        $this->app->instance(ModuleManifestRepositoryInterface::class, $manifests);
        $this->app->instance(ModuleStateRepositoryInterface::class, $stateRepo);
        $this->app->instance(ModuleDependencyGuard::class, $this->lifecycleDependencyGuard($registry));
        $this->app->instance(LifecycleRegistryInvalidator::class, $this->lifecycleInvalidator($cache, $registry));
        $this->app->instance(ModuleDirectoryOperations::class, $this->lifecycleDirectoryOps($this->lifecycleDirectoryPaths($config)));

        $this->app->make(Kernel::class)->registerCommand($this->app->make(ModulesRemoveCommand::class));
    }

    private function installModule(string $name): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, schema: new \stdClass());
        $this->writeModuleState($this->stateRoot, $name, true, values: new \stdClass());
    }
}
