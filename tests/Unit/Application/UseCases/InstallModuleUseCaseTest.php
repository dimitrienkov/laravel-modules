<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\UseCases\InstallModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleInstallException;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class InstallModuleUseCaseTest extends TestCase
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

    private function makeUseCase(
        ?\DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface $manifestRepository = null,
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
            new \DimitrienkoV\LaravelModules\Application\Support\PartialModuleRollback($directoryOps, $stateRepo),
        );
    }

    private function createSourceZip(string $name): string
    {
        $manifest = json_encode([
            'schema_version' => 1,
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'kind' => 'module', 'version' => '1.0.0'],
            'settings' => ['schema' => new \stdClass()],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $zipPath = $this->tempDir . '/sources/' . $name . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('module.json', $manifest);
        $zip->close();

        return $zipPath;
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function createSourceZipWithDeps(string $name, array $dependencies): string
    {
        $manifest = json_encode([
            'schema_version' => 1,
            'meta' => [
                'name' => $name,
                'display_name' => ucfirst($name),
                'kind' => 'module',
                'version' => '1.0.0',
                'dependencies' => $dependencies,
            ],
            'settings' => ['schema' => new \stdClass()],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $zipPath = $this->tempDir . '/sources/' . $name . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('module.json', $manifest);
        $zip->close();

        return $zipPath;
    }

    private function createInstalledModule(string $name): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name);
        $this->writeModuleState($this->stateRoot, $name, values: new \stdClass());
    }
}
