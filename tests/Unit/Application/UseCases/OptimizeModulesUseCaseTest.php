<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\UseCases\OptimizeModulesUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistrySnapshotBuilder;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptimizeModulesUseCase::class)]
#[Group('lifecycle')]
final class OptimizeModulesUseCaseTest extends TestCase
{
    use CreatesModuleFiles;
    use MockeryPHPUnitIntegration;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-opt-uc-' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function writesCacheFromFreshScan(): void
    {
        $this->writeModule('users', '1.0.0');
        $this->writeModule('blog', '1.0.0', ['users' => '^1.0']);

        $cache = Mockery::mock(ModuleRegistryCacheInterface::class);
        $cache->expects('write')
            ->once()
            ->withArgs(function (array $loadOrder): bool {
                return \count($loadOrder) === 2
                    && $loadOrder[0]->name === 'users'
                    && $loadOrder[1]->name === 'blog';
            })
            ->andReturn('/path/to/cache/modules.php');

        $useCase = new OptimizeModulesUseCase($this->builder(), $cache);
        $result = $useCase->execute();

        self::assertSame('/path/to/cache/modules.php', $result->path);
        self::assertSame(2, $result->count);
    }

    #[Test]
    public function returnsCorrectCountForSingleModule(): void
    {
        $this->writeModule('blog', '1.0.0');

        $cache = Mockery::mock(ModuleRegistryCacheInterface::class);
        $cache->expects('write')->once()->andReturn('/cache/modules.php');

        $useCase = new OptimizeModulesUseCase($this->builder(), $cache);
        $result = $useCase->execute();

        self::assertSame(1, $result->count);
    }

    #[Test]
    public function emitsStartedThenSucceededOnceOnTheHappyPath(): void
    {
        $this->writeModule('blog', '1.0.0');

        $cache = Mockery::mock(ModuleRegistryCacheInterface::class);
        $cache->expects('write')->once()->andReturn('/cache/modules.php');

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $useCase = new OptimizeModulesUseCase($this->builder(), $cache, $diagnostics);
        $useCase->execute();

        $diagnostics->shouldHaveReceived('lifecycleStarted')->once()->with(LifecycleOperation::Optimize);
        $diagnostics->shouldHaveReceived('lifecycleSucceeded')->once()->with(LifecycleOperation::Optimize);
        $diagnostics->shouldNotHaveReceived('lifecycleFailed');
    }

    private function builder(): ModuleRegistrySnapshotBuilder
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                    'state' => $this->stateRoot,
                ],
            ],
        ]);

        $stateRepo = new ModuleStateRepository(
            paths: new ModuleStatePaths(config: $config, basePath: $this->tempDir),
            writer: new AtomicJsonWriter(),
            filesystem: new LocalFilesystem(new Filesystem()),
        );

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
                stateRepository: $stateRepo,
                filesystem: new LocalFilesystem(new Filesystem()),
            ),
            sorter: new TopologicalSorter(),
        );
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function writeModule(string $name, string $version, array $dependencies = []): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, $version, $dependencies, schema: []);
        $this->writeModuleState($this->stateRoot, $name, values: new \stdClass());
    }

}
