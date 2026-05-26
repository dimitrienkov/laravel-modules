<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\UseCases\RemoveModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\DependentModulesExistException;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoveModuleUseCaseTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;

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

        $result = $useCase->execute('blog', deletePermanently: true);

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

        $result = $useCase->execute('blog', deletePermanently: true);

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

    private function makeUseCase(): RemoveModuleUseCase
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
