<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesRemoveCommand;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesRemoveCommandTest extends TestCase
{
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
        $app = $this->app;
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => [
                'directories' => ['app/Modules'],
                'backup' => $this->tempDir . '/backups',
                'state' => $this->stateRoot,
            ]],
        ]);

        $statePaths = new ModuleStatePaths(config: $config, basePath: $this->tempDir);
        $stateRepository = new ModuleStateRepository(
            paths: $statePaths,
            writer: new AtomicJsonWriter(),
            filesystem: new Filesystem(),
        );

        $manifests = new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
            documentReader: new ManifestDocumentReader(),
            stateRepository: $stateRepository,
        );

        $sorter = new TopologicalSorter();
        $cache = new ModuleRegistryCache(
            validator: $validator,
            layout: $layout,
            stateRepository: $stateRepository,
            basePath: $this->tempDir,
        );

        $registry = new ModuleRegistry(
            manifests: $manifests,
            sorter: $sorter,
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new Filesystem(),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            cache: $cache,
        );

        $guard = new ModuleDependencyGuard($registry, $sorter);
        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);
        $paths = new ModuleDirectoryPaths($config, $this->tempDir, $this->tempDir . '/app');
        $directoryOps = new ModuleDirectoryOperations(new Filesystem(), $paths);

        $app->instance(ModuleRegistryInterface::class, $registry);
        $app->instance(ModuleManifestRepositoryInterface::class, $manifests);
        $app->instance(ModuleStateRepositoryInterface::class, $stateRepository);
        $app->instance(ModuleDependencyGuard::class, $guard);
        $app->instance(LifecycleRegistryInvalidator::class, $invalidator);
        $app->instance(ModuleDirectoryOperations::class, $directoryOps);

        $app->make(Kernel::class)->registerCommand($app->make(ModulesRemoveCommand::class));
    }

    private function installModule(string $name): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, schema: new \stdClass());
        $this->writeModuleState($this->stateRoot, $name, true, values: new \stdClass());
    }
}
