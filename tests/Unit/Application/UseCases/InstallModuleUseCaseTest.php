<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\Support\PartialModuleRollback;
use DimitrienkoV\LaravelModules\Application\UseCases\InstallModuleUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleInstallException;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesSourceArchive;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstallModuleUseCase::class)]
#[Group('lifecycle')]
final class InstallModuleUseCaseTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;
    use CreatesSourceArchive;
    use MockeryPHPUnitIntegration;

    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/install_test_' . uniqid();
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        $this->filesystem->makeDirectory($this->tempDir . '/bootstrap/cache', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/app/Modules', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/sources', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function installFromZipSource(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute($zipPath);

        $this->assertSame('blog', $result->name);
        $this->assertTrue($result->enabled);
        $this->assertSame('zip', $result->sourceKind->value);
        $this->assertDirectoryExists($result->path);
        $this->assertFileExists($result->path . '/module.json');
        $this->assertFileExists($this->stateRoot . '/blog/state.json');
    }

    #[Test]
    public function installWritesZipSourceOriginWithChecksum(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $useCase = $this->makeUseCase();

        $useCase->execute($zipPath);

        $state = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertArrayHasKey('source', $state);
        $this->assertSame('zip', $state['source']['kind']);
        $this->assertSame('1.0.0', $state['source']['installed_version']);
        $this->assertNotEmpty($state['source']['checksum']);
        $this->assertSame(64, \strlen($state['source']['checksum']));
    }

    #[Test]
    public function installDisabled(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute($zipPath, enabled: false);

        $this->assertFalse($result->enabled);
        $state = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertFalse($state['enabled']);
    }

    #[Test]
    public function installThrowsWhenModuleAlreadyInRegistry(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $this->createInstalledModule('blog');
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleAlreadyExistsException::class);
        $useCase->execute($zipPath);
    }

    #[Test]
    public function installRollsBackOnStatePersistenceFailure(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $targetPath = $this->tempDir . '/app/Modules/Blog';

        $failingManifests = $this->createMock(\DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface::class);
        $failingManifests->method('writeManifest')->willThrowException(
            new \DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException('simulated write failure'),
        );

        $useCase = $this->makeUseCase(manifestRepository: $failingManifests);

        try {
            $useCase->execute($zipPath);
            $this->fail('Expected ModuleInstallException was not thrown');
        } catch (ModuleInstallException $e) {
            $this->assertStringContainsString('persistence failed', $e->getMessage());
            $this->assertDirectoryDoesNotExist($targetPath);
            $this->assertFalse(is_dir($this->stateRoot . '/blog'));
        }
    }

    #[Test]
    public function installCleansUpPreparedSourceOnSuccess(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $useCase = $this->makeUseCase();

        $before = glob(sys_get_temp_dir() . '/module_zip_*') ?: [];

        $useCase->execute($zipPath);

        $after = glob(sys_get_temp_dir() . '/module_zip_*') ?: [];
        $leaked = array_values(array_diff($after, $before));

        $this->assertSame([], $leaked, 'Prepared source temp directory should be removed after a successful install.');
    }

    #[Test]
    public function installThrowsWhenTargetDirectoryAlreadyExists(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $targetPath = $this->tempDir . '/app/Modules/Blog';
        $this->filesystem->makeDirectory($targetPath, 0755, true);

        $useCase = $this->makeUseCase();

        $this->expectException(ModuleAlreadyExistsException::class);
        $useCase->execute($zipPath);
    }

    #[Test]
    public function installModuleWithDependencies(): void
    {
        $this->createInstalledModule('users');

        $zipPath = $this->createSourceZipWithDeps('blog', ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute($zipPath);

        $this->assertSame('blog', $result->name);
        $this->assertTrue($result->enabled);
    }

    #[Test]
    public function emitsLifecycleFailedAndNoSuccessWhenPersistenceFails(): void
    {
        $zipPath = $this->createSourceZip('blog');

        $failingManifests = $this->createMock(ModuleManifestRepositoryInterface::class);
        $failingManifests->method('writeManifest')->willThrowException(
            new ManifestWriteException('simulated write failure'),
        );

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $useCase = $this->makeUseCase(manifestRepository: $failingManifests, diagnostics: $diagnostics);

        try {
            $useCase->execute($zipPath);
            $this->fail('Expected ModuleInstallException');
        } catch (ModuleInstallException) {
            // expected
        }

        $diagnostics->shouldHaveReceived('lifecycleStarted')->once()->with(LifecycleOperation::Install, 'blog', 'zip');
        $diagnostics->shouldHaveReceived('lifecycleFailed')->once()->with(
            LifecycleOperation::Install,
            'blog',
            Mockery::type(ModuleInstallException::class),
        );
        $diagnostics->shouldNotHaveReceived('lifecycleSucceeded');
    }

    #[Test]
    public function guardRejectionBeforeStartEmitsNoLifecycleEvents(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $this->createInstalledModule('blog');

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $useCase = $this->makeUseCase(diagnostics: $diagnostics);

        try {
            $useCase->execute($zipPath);
            $this->fail('Expected ModuleAlreadyExistsException');
        } catch (ModuleAlreadyExistsException) {
            // expected
        }

        $diagnostics->shouldNotHaveReceived('lifecycleStarted');
        $diagnostics->shouldNotHaveReceived('lifecycleFailed');
    }

    private function makeUseCase(
        ?ModuleManifestRepositoryInterface $manifestRepository = null,
        ?ModuleDiagnosticsInterface $diagnostics = null,
    ): InstallModuleUseCase {
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);
        $paths = $this->lifecycleDirectoryPaths($config);

        $directoryOps = $this->lifecycleDirectoryOps($paths);

        return new InstallModuleUseCase(
            $registry,
            $manifestRepository ?? $manifests,
            $stateRepo,
            $this->lifecycleSourcePreparer(),
            $paths,
            $this->lifecycleDependencyGuard($registry),
            $directoryOps,
            $this->lifecycleInvalidator($cache, $registry),
            $this->lifecycleNamespaceResolver(),
            new PartialModuleRollback($directoryOps, $stateRepo),
            $diagnostics ?? new NullModuleDiagnostics(),
        );
    }

    private function createSourceZip(string $name): string
    {
        return $this->zipModuleSource(
            $this->tempDir . '/sources/' . $name . '.zip',
            $this->moduleManifestArray($name),
        );
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function createSourceZipWithDeps(string $name, array $dependencies): string
    {
        return $this->zipModuleSource(
            $this->tempDir . '/sources/' . $name . '.zip',
            $this->moduleManifestArray($name, dependencies: $dependencies),
        );
    }

    private function createInstalledModule(string $name): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name);
        $this->writeModuleState($this->stateRoot, $name, values: new \stdClass());
    }
}
