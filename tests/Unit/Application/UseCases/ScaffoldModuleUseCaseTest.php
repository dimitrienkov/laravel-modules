<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleConfig;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSkeletonBuilder;
use DimitrienkoV\LaravelModules\Application\Support\PartialModuleRollback;
use DimitrienkoV\LaravelModules\Application\UseCases\ScaffoldModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleScaffoldException;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScaffoldModuleUseCaseTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use MockeryPHPUnitIntegration;

    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/scaffold_test_' . uniqid();
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        $this->filesystem->makeDirectory($this->tempDir . '/bootstrap/cache', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/app/Modules', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function scaffoldsNewModule(): void
    {
        $useCase = $this->makeUseCase();

        $result = $useCase->execute(new ScaffoldModuleConfig(name: 'blog'));

        $this->assertSame('blog', $result->name);
        $this->assertTrue($result->enabled);
        $this->assertDirectoryExists($result->path);
        $this->assertFileExists($result->path . '/module.json');
        $this->assertFileExists($this->stateRoot . '/blog/state.json');
        $this->assertDirectoryExists($result->path . '/Providers');
        $this->assertDirectoryExists($result->path . '/Config');
        $this->assertDirectoryExists($result->path . '/Routes');
    }

    #[Test]
    public function scaffoldCreatesProviderStub(): void
    {
        $useCase = $this->makeUseCase();

        $result = $useCase->execute(new ScaffoldModuleConfig(name: 'blog'));

        $providerFile = $result->path . '/Providers/BlogServiceProvider.php';
        $this->assertFileExists($providerFile);
        $content = file_get_contents($providerFile);
        $this->assertStringContainsString('declare(strict_types=1)', $content);
        $this->assertStringContainsString('BlogServiceProvider', $content);
    }

    #[Test]
    public function scaffoldDisabledModule(): void
    {
        $useCase = $this->makeUseCase();

        $result = $useCase->execute(new ScaffoldModuleConfig(name: 'blog', enabled: false));

        $this->assertFalse($result->enabled);
        $state = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertFalse($state['enabled']);
    }

    #[Test]
    public function scaffoldThrowsOnInvalidName(): void
    {
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleScaffoldException::class);
        $this->expectExceptionMessageMatches('/invalid module name/');

        $useCase->execute(new ScaffoldModuleConfig(name: 'Invalid-Name!'));
    }

    #[Test]
    public function scaffoldThrowsWhenModuleExists(): void
    {
        $useCase = $this->makeUseCase();
        $useCase->execute(new ScaffoldModuleConfig(name: 'blog'));

        $this->expectException(ModuleAlreadyExistsException::class);

        $useCase2 = $this->makeUseCase();
        $useCase2->execute(new ScaffoldModuleConfig(name: 'blog'));
    }

    #[Test]
    public function scaffoldForceOverwritesExisting(): void
    {
        $useCase = $this->makeUseCase();
        $useCase->execute(new ScaffoldModuleConfig(name: 'blog'));

        $useCase2 = $this->makeUseCase();
        $result = $useCase2->execute(new ScaffoldModuleConfig(name: 'blog', force: true));

        $this->assertSame('blog', $result->name);
        $this->assertFileExists($result->path . '/module.json');
    }

    #[Test]
    public function scaffoldWritesValidManifestAndState(): void
    {
        $useCase = $this->makeUseCase();

        $result = $useCase->execute(new ScaffoldModuleConfig(name: 'user_auth'));

        $manifest = json_decode(file_get_contents($result->path . '/module.json'), true);
        $this->assertSame('user_auth', $manifest['meta']['name']);
        $this->assertSame('1.0.0', $manifest['meta']['version']);
        $this->assertArrayNotHasKey('state', $manifest);

        $state = json_decode(file_get_contents($this->stateRoot . '/user_auth/state.json'), true);
        $this->assertNotNull($state['installed_at']);
    }

    #[Test]
    public function scaffoldForceThrowsWhenDirectoryCannotBeDeleted(): void
    {
        $useCase = $this->makeUseCase();
        $useCase->execute(new ScaffoldModuleConfig(name: 'blog'));

        /** @var Filesystem&Mockery\MockInterface $failingFs */
        $failingFs = Mockery::mock(Filesystem::class)->makePartial();
        $failingFs->shouldReceive('deleteDirectory')->andReturn(false);
        $failingFs->shouldReceive('isDirectory')->andReturn(true);

        $failingLocalFs = new LocalFilesystem($failingFs);
        $config = $this->lifecycleConfig();
        $paths = $this->lifecycleDirectoryPaths($config);
        $failingDirOps = new ModuleDirectoryOperations($failingLocalFs, $paths);

        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);
        $cache = $this->lifecycleRegistryCache($stateRepo);

        $useCase2 = new ScaffoldModuleUseCase(
            registry: $registry,
            manifestRepository: $manifests,
            stateRepository: $stateRepo,
            namespaceResolver: $this->lifecycleNamespaceResolver(),
            paths: $paths,
            invalidator: $this->lifecycleInvalidator($cache, $registry),
            skeletonBuilder: new ModuleSkeletonBuilder(new LocalFilesystem(new Filesystem()), new AtomicFileWriter(), \dirname(__DIR__, 4) . '/stubs'),
            directoryOps: $failingDirOps,
            rollback: new PartialModuleRollback($failingDirOps, $stateRepo),
        );

        $this->expectException(ModuleScaffoldException::class);
        $this->expectExceptionMessageMatches('/failed to remove existing directory/');

        $useCase2->execute(new ScaffoldModuleConfig(name: 'blog', force: true));
    }

    #[Test]
    public function scaffoldForceRejectsNameCollisionInDifferentRoot(): void
    {
        $this->filesystem->makeDirectory($this->tempDir . '/app/OtherModules', 0755, true);

        $useCase1 = $this->makeUseCaseWithRoots(['app/Modules', 'app/OtherModules']);
        $useCase1->execute(new ScaffoldModuleConfig(name: 'blog'));

        $useCase2 = $this->makeUseCaseWithRoots(['app/Modules', 'app/OtherModules']);

        $this->expectException(ModuleAlreadyExistsException::class);

        $useCase2->execute(new ScaffoldModuleConfig(name: 'blog', directory: 'app/OtherModules', force: true));
    }

    /**
     * @param array<int, string> $roots
     */
    private function makeUseCaseWithRoots(array $roots): ScaffoldModuleUseCase
    {
        $config = $this->lifecycleConfig(directories: $roots);
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $paths = $this->lifecycleDirectoryPaths($config);

        $directoryOps = $this->lifecycleDirectoryOps($paths);

        return new ScaffoldModuleUseCase(
            registry: $registry,
            manifestRepository: $manifests,
            stateRepository: $stateRepo,
            namespaceResolver: $this->lifecycleNamespaceResolver(),
            paths: $paths,
            invalidator: $this->lifecycleInvalidator($cache, $registry),
            skeletonBuilder: new ModuleSkeletonBuilder(new LocalFilesystem(new Filesystem()), new AtomicFileWriter(), \dirname(__DIR__, 4) . '/stubs'),
            directoryOps: $directoryOps,
            rollback: new \DimitrienkoV\LaravelModules\Application\Support\PartialModuleRollback($directoryOps, $stateRepo),
        );
    }

    private function makeUseCase(): ScaffoldModuleUseCase
    {
        return $this->makeUseCaseWithRoots(['app/Modules']);
    }
}
