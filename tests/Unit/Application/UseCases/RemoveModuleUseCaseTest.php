<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\Enums\RemoveStrategy;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\UseCases\RemoveModuleUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\DependentModulesExistException;
use DimitrienkoV\LaravelModules\Exceptions\DirectoryOperationException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleRemoveException;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveModuleUseCase::class)]
#[Group('lifecycle')]
final class RemoveModuleUseCaseTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;
    use MockeryPHPUnitIntegration;

    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/remove_test_' . uniqid();
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        $this->filesystem->makeDirectory($this->tempDir . '/bootstrap/cache', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/app/Modules', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/backups', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function removesModuleWithBackup(): void
    {
        $this->createModule('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertSame('blog', $result->name);
        $this->assertNotNull($result->backupPath);
        $this->assertDirectoryExists($result->backupPath);
        $this->assertDirectoryDoesNotExist($this->tempDir . '/app/Modules/Blog');
    }

    #[Test]
    public function removesModuleWithoutBackup(): void
    {
        $this->createModule('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog', strategy: RemoveStrategy::Permanent);

        $this->assertNull($result->backupPath);
        $this->assertDirectoryDoesNotExist($this->tempDir . '/app/Modules/Blog');
        $this->assertFileDoesNotExist($this->stateRoot . '/blog/state.json');
    }

    #[Test]
    public function blockedByEnabledDependent(): void
    {
        $this->createModule('users');
        $this->createModule('blog', dependencies: ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $this->expectException(DependentModulesExistException::class);
        $useCase->execute('users');
    }

    #[Test]
    public function blockedByDisabledDependent(): void
    {
        $this->createModule('users');
        $this->createModule('blog', enabled: false, dependencies: ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $this->expectException(DependentModulesExistException::class);
        $useCase->execute('users');
    }

    #[Test]
    public function permanentDeleteRemovesDirectoryBeforeState(): void
    {
        $this->createModule('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog', strategy: RemoveStrategy::Permanent);

        $this->assertNull($result->backupPath);
        $this->assertDirectoryDoesNotExist($this->tempDir . '/app/Modules/Blog');
        $this->assertDirectoryDoesNotExist($this->stateRoot . '/blog');
    }

    #[Test]
    public function removesModuleWithNoDependents(): void
    {
        $this->createModule('blog');
        $this->createModule('users');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertSame('blog', $result->name);
    }

    #[Test]
    public function permanentDeleteRestoresStateWhenDirectoryRemovalFails(): void
    {
        $this->createModule('blog');
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        /** @var Filesystem&Mockery\MockInterface $failingFs */
        $failingFs = Mockery::mock(Filesystem::class)->makePartial();
        $failingFs->shouldReceive('deleteDirectory')->andReturn(false);
        $failingFs->shouldReceive('isDirectory')->andReturn(true);

        $failingDirOps = new ModuleDirectoryOperations(
            new LocalFilesystem($failingFs),
            $this->lifecycleDirectoryPaths($config),
        );

        $useCase = new RemoveModuleUseCase(
            $registry,
            $stateRepo,
            $this->lifecycleDependencyGuard($registry),
            $failingDirOps,
            $this->lifecycleInvalidator($cache, $registry),
        );

        try {
            $useCase->execute('blog', strategy: RemoveStrategy::Permanent);
            $this->fail('Expected ModuleRemoveException');
        } catch (ModuleRemoveException $e) {
            $this->assertStringContainsString('directory removal failed, restored state', $e->getMessage());
        }

        $this->assertFileExists($this->stateRoot . '/blog/state.json');
    }

    #[Test]
    public function permanentDeleteDoubleFaultWhenStateRestoreAlsoFails(): void
    {
        $this->createModule('blog');
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');
        $realStateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($realStateRepo);
        $cache = $this->lifecycleRegistryCache($realStateRepo);
        $registry = $this->lifecycleRegistry($manifests, $realStateRepo, $config);

        $savedDocument = $realStateRepo->read('blog', $registry->find('blog'));

        /** @var Filesystem&Mockery\MockInterface $failingFs */
        $failingFs = Mockery::mock(Filesystem::class)->makePartial();
        $failingFs->shouldReceive('deleteDirectory')->andReturn(false);
        $failingFs->shouldReceive('isDirectory')->andReturn(true);

        $failingDirOps = new ModuleDirectoryOperations(
            new LocalFilesystem($failingFs),
            $this->lifecycleDirectoryPaths($config),
        );

        /** @var ModuleStateRepositoryInterface&Mockery\MockInterface $failingStateRepo */
        $failingStateRepo = Mockery::mock(ModuleStateRepositoryInterface::class);
        $failingStateRepo->shouldReceive('read')->andReturn($savedDocument);
        $failingStateRepo->shouldReceive('delete')->once();
        $failingStateRepo->shouldReceive('writeDocument')
            ->andThrow(new \RuntimeException('state write failed'));

        $useCase = new RemoveModuleUseCase(
            $registry,
            $failingStateRepo,
            $this->lifecycleDependencyGuard($registry),
            $failingDirOps,
            $this->lifecycleInvalidator($cache, $registry),
        );

        try {
            $useCase->execute('blog', strategy: RemoveStrategy::Permanent);
            $this->fail('Expected ModuleRemoveException');
        } catch (ModuleRemoveException $e) {
            $this->assertStringContainsString('state restore also failed', $e->getMessage());
            $this->assertStringContainsString('Restore error:', $e->getMessage());
            $this->assertInstanceOf(DirectoryOperationException::class, $e->getPrevious());
        }
    }

    #[Test]
    public function backupModeDoubleFaultPreservesRestoreErrorInChain(): void
    {
        $this->createModule('blog');
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        /** @var ModuleStateRepositoryInterface&Mockery\MockInterface $failingStateRepo */
        $failingStateRepo = Mockery::mock(ModuleStateRepositoryInterface::class);
        $failingStateRepo->shouldReceive('moveToBackup')
            ->andThrow(new \RuntimeException('state backup failed'));

        $moveCount = 0;
        /** @var Filesystem&Mockery\MockInterface $failingFs */
        $failingFs = Mockery::mock(Filesystem::class)->makePartial();
        $failingFs->shouldReceive('moveDirectory')
            ->andReturnUsing(function (string $source, string $target) use (&$moveCount): bool {
                $moveCount++;
                if ($moveCount > 1) {
                    return false;
                }

                return (new Filesystem())->moveDirectory($source, $target);
            });
        $failingFs->shouldReceive('isDirectory')->andReturnUsing(
            fn (string $path): bool => (new Filesystem())->isDirectory($path),
        );
        $failingFs->shouldReceive('makeDirectory')->andReturnUsing(
            fn (string $path, int $mode, bool $recursive): bool => (new Filesystem())->makeDirectory($path, $mode, $recursive),
        );

        $failingDirOps = new ModuleDirectoryOperations(
            new LocalFilesystem($failingFs),
            $this->lifecycleDirectoryPaths($config),
        );

        $useCase = new RemoveModuleUseCase(
            $registry,
            $failingStateRepo,
            $this->lifecycleDependencyGuard($registry),
            $failingDirOps,
            $this->lifecycleInvalidator($cache, $registry),
        );

        try {
            $useCase->execute('blog', strategy: RemoveStrategy::Backup);
            $this->fail('Expected ModuleRemoveException');
        } catch (ModuleRemoveException $e) {
            $this->assertStringContainsString('restore also failed', $e->getMessage());
            $restoreError = $e->getPrevious();
            $this->assertInstanceOf(\RuntimeException::class, $restoreError);
            $this->assertStringContainsString('state backup failed', $restoreError->getMessage());
        }
    }

    #[Test]
    public function emitsLifecycleFailedWhenDirectoryRemovalFails(): void
    {
        $this->createModule('blog');
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        /** @var Filesystem&Mockery\MockInterface $failingFs */
        $failingFs = Mockery::mock(Filesystem::class)->makePartial();
        $failingFs->shouldReceive('deleteDirectory')->andReturn(false);
        $failingFs->shouldReceive('isDirectory')->andReturn(true);

        $failingDirOps = new ModuleDirectoryOperations(
            new LocalFilesystem($failingFs),
            $this->lifecycleDirectoryPaths($config),
        );

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $useCase = new RemoveModuleUseCase(
            $registry,
            $stateRepo,
            $this->lifecycleDependencyGuard($registry),
            $failingDirOps,
            $this->lifecycleInvalidator($cache, $registry),
            $diagnostics,
        );

        try {
            $useCase->execute('blog', strategy: RemoveStrategy::Permanent);
            $this->fail('Expected ModuleRemoveException');
        } catch (ModuleRemoveException) {
            // expected
        }

        $diagnostics->shouldHaveReceived('lifecycleStarted')->once()->with(LifecycleOperation::Remove, 'blog');
        $diagnostics->shouldHaveReceived('lifecycleFailed')->once()->with(
            LifecycleOperation::Remove,
            'blog',
            Mockery::type(ModuleRemoveException::class),
        );
        $diagnostics->shouldNotHaveReceived('lifecycleSucceeded');
    }

    #[Test]
    public function dependencyGuardRejectionEmitsNoLifecycleEvents(): void
    {
        $this->createModule('users');
        $this->createModule('blog', dependencies: ['users' => '^1.0']);

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $useCase = $this->makeUseCase($diagnostics);

        try {
            $useCase->execute('users');
            $this->fail('Expected DependentModulesExistException');
        } catch (DependentModulesExistException) {
            // expected
        }

        // assertCanRemove runs before lifecycleStarted: a precondition rejection
        // is not a started-and-failed operation, so it emits nothing.
        $diagnostics->shouldNotHaveReceived('lifecycleStarted');
        $diagnostics->shouldNotHaveReceived('lifecycleFailed');
    }

    private function makeUseCase(?ModuleDiagnosticsInterface $diagnostics = null): RemoveModuleUseCase
    {
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        return new RemoveModuleUseCase(
            $registry,
            $stateRepo,
            $this->lifecycleDependencyGuard($registry),
            $this->lifecycleDirectoryOps($this->lifecycleDirectoryPaths($config)),
            $this->lifecycleInvalidator($cache, $registry),
            $diagnostics ?? new NullModuleDiagnostics(),
        );
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function createModule(string $name, bool $enabled = true, array $dependencies = []): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, dependencies: $dependencies);
        $this->writeModuleState($this->stateRoot, $name, $enabled, values: new \stdClass());
    }
}
