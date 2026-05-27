<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\UseCases\ClearModulesOptimizeCacheUseCase;
use DimitrienkoV\LaravelModules\Application\UseCases\OptimizeModulesUseCase;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeClearCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeCommand;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistrySnapshotBuilder;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesOptimizeCommandTest extends TestCase
{
    use CreatesModuleFiles;
    private string $modulePath;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-optimize-' . bin2hex(random_bytes(6));
        $this->modulePath = $this->tempDir . '/app/Modules/Blog';

        mkdir($this->modulePath, 0755, true);
        $this->writeManifest();

        $app = $this->application();
        $cache = $this->registryCache();
        $builder = $this->snapshotBuilder();
        $registry = $this->registry($builder, $cache);

        $app->instance(ModuleRegistryCache::class, $cache);
        $app->instance(ModuleRegistrySnapshotBuilder::class, $builder);
        $app->instance(ModuleRegistry::class, $registry);

        $optimizeUseCase = new OptimizeModulesUseCase($builder, $cache);
        $app->instance(OptimizeModulesUseCase::class, $optimizeUseCase);

        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);
        $clearUseCase = new ClearModulesOptimizeCacheUseCase($cache, $invalidator);
        $app->instance(ClearModulesOptimizeCacheUseCase::class, $clearUseCase);

        $app->make(Kernel::class)->registerCommand($app->make(ModulesOptimizeCommand::class));
        $app->make(Kernel::class)->registerCommand($app->make(ModulesOptimizeClearCommand::class));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function optimize_writes_v4_module_cache(): void
    {
        $this->artisanCommand('modules:optimize')->assertSuccessful();

        $cachePath = $this->tempDir . '/bootstrap/cache/modules.php';
        self::assertFileExists($cachePath);

        $payload = require $cachePath;

        self::assertSame(4, $payload['version']);
        self::assertSame(['blog'], $payload['load_order']);
        self::assertSame('App\\Modules\\Blog', $payload['modules']['blog']['namespace']);
    }

    #[Test]
    public function optimize_clear_removes_only_v4_module_cache(): void
    {
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
        file_put_contents($this->tempDir . '/bootstrap/cache/modules.php', '<?php return [];');
        file_put_contents($this->tempDir . '/bootstrap/cache/modules-providers.php', '<?php return [];');

        $this->artisanCommand('modules:optimize-clear')->assertSuccessful();

        self::assertFileDoesNotExist($this->tempDir . '/bootstrap/cache/modules.php');
        self::assertFileExists($this->tempDir . '/bootstrap/cache/modules-providers.php');
    }

    #[Test]
    public function optimize_clear_resets_in_memory_registry_in_same_process(): void
    {
        /** @var ModuleRegistry $registry */
        $registry = $this->application()->make(ModuleRegistry::class);

        self::assertSame(['blog'], array_map(
            static fn (object $module): string => $module->name,
            $registry->all(),
        ));

        $usersPath = $this->tempDir . '/app/Modules/Users';
        mkdir($usersPath, 0755, true);
        $this->writeManifest($usersPath, 'users', 'Users');
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
        file_put_contents($this->tempDir . '/bootstrap/cache/modules.php', '<?php return [];');

        $this->artisanCommand('modules:optimize-clear')->assertSuccessful();

        self::assertSame(['blog', 'users'], array_map(
            static fn (object $module): string => $module->name,
            $registry->all(),
        ));
    }

    private function stateRepository(): ModuleStateRepository
    {
        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                ],
            ],
        ]);

        return new ModuleStateRepository(
            paths: new ModuleStatePaths(config: $config, basePath: $this->tempDir),
            writer: new AtomicJsonWriter(),
            filesystem: new LocalFilesystem(new Filesystem()),
        );
    }

    private function registryCache(): ModuleRegistryCache
    {
        return new ModuleRegistryCache(
            validator: new ManifestValidator(new ManifestSettingsValidator()),
            layout: new ModuleLayout(),
            stateRepository: $this->stateRepository(),
            basePath: $this->tempDir,
        );
    }

    private function snapshotBuilder(): ModuleRegistrySnapshotBuilder
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                ],
            ],
        ]);

        $stateRepository = $this->stateRepository();

        return new ModuleRegistrySnapshotBuilder(
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new LocalFilesystem(new Filesystem()),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            manifests: new ModuleManifestRepository(
                layout: $layout,
                writer: new AtomicJsonWriter(),
                validator: $validator,
                namespaceResolver: new FakeNamespaceResolver($this->tempDir),
                documentReader: new ManifestDocumentReader(),
                stateRepository: $stateRepository,
                filesystem: new LocalFilesystem(new Filesystem()),
            ),
            sorter: new TopologicalSorter(),
        );
    }

    private function registry(
        ModuleRegistrySnapshotBuilder $builder,
        ModuleRegistryCache $cache,
    ): ModuleRegistry {
        return new ModuleRegistry(
            builder: $builder,
            cache: $cache,
        );
    }

    private function application(): Application
    {
        if ($this->app === null) {
            self::fail('Testbench application is not initialized.');
        }

        return $this->app;
    }

    private function artisanCommand(string $command): PendingCommand
    {
        $pendingCommand = $this->artisan($command);

        if (! $pendingCommand instanceof PendingCommand) {
            self::fail("Command [{$command}] did not return a pending command instance.");
        }

        return $pendingCommand;
    }

    private function writeManifest(
        ?string $modulePath = null,
        string $name = 'blog',
        string $displayName = 'Blog',
    ): void {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, schema: []);
        $this->writeModuleState($this->tempDir . '/storage/app/private/modules', $name);
    }

}
