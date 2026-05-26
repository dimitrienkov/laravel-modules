<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\MakeModuleCommand;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
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
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MakeModuleCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/make_module_cmd_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);

        $this->registerServices();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function scaffoldsModuleSuccessfully(): void
    {
        $this->artisanCommand('make:module blog')
            ->assertSuccessful()
            ->expectsOutputToContain('scaffolded');

        $this->assertDirectoryExists($this->tempDir . '/app/Modules/Blog');
        $this->assertFileExists($this->tempDir . '/app/Modules/Blog/module.json');
    }

    #[Test]
    public function scaffoldsDisabledModule(): void
    {
        $this->artisanCommand('make:module blog --disabled')
            ->assertSuccessful();

        $stateFile = $this->tempDir . '/storage/app/private/modules/blog/state.json';
        $this->assertFileExists($stateFile);
        $state = json_decode(
            file_get_contents($stateFile),
            true,
        );
        $this->assertFalse($state['enabled']);
    }

    #[Test]
    public function failsOnInvalidName(): void
    {
        $this->artisanCommand('make:module "Invalid Name!"')
            ->assertFailed()
            ->expectsOutputToContain('invalid module name');
    }

    #[Test]
    public function failsWhenModuleAlreadyExists(): void
    {
        $this->artisanCommand('make:module blog')->assertSuccessful();

        $this->registerServices();

        $this->artisanCommand('make:module blog')
            ->assertFailed()
            ->expectsOutputToContain('already exists');
    }

    #[Test]
    public function forceOverwritesExisting(): void
    {
        $this->artisanCommand('make:module blog')->assertSuccessful();

        $this->registerServices();

        $this->artisanCommand('make:module blog --force')
            ->assertSuccessful();
    }

    private function registerServices(): void
    {
        $app = $this->app;
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules']]],
        ]);

        $namespaceResolver = new FakeNamespaceResolver($this->tempDir);
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
            namespaceResolver: $namespaceResolver,
            documentReader: new ManifestDocumentReader(),
            stateRepository: $stateRepository,
        );

        $cache = new ModuleRegistryCache(
            validator: $validator,
            layout: $layout,
            stateRepository: $stateRepository,
            basePath: $this->tempDir,
        );

        $registry = new ModuleRegistry(
            manifests: $manifests,
            sorter: new TopologicalSorter(),
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new Filesystem(),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            cache: $cache,
        );

        $paths = new ModuleDirectoryPaths($config, $this->tempDir, $this->tempDir . '/app');
        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        $app->instance(ModuleRegistryInterface::class, $registry);
        $app->instance(ModuleManifestRepositoryInterface::class, $manifests);
        $app->instance(ModuleStateRepositoryInterface::class, $stateRepository);
        $app->instance(ManifestValidatorInterface::class, $validator);
        $app->instance(NamespaceResolverInterface::class, $namespaceResolver);
        $app->instance(ModuleDirectoryPaths::class, $paths);
        $app->instance(LifecycleRegistryInvalidator::class, $invalidator);

        $app->make(Kernel::class)->registerCommand($app->make(MakeModuleCommand::class));
    }

    private function artisanCommand(string $command): PendingCommand
    {
        $result = $this->artisan($command);
        \assert($result instanceof PendingCommand);

        return $result;
    }
}
