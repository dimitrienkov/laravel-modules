<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleLifecyclePaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Application\UseCases\InstallModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class InstallModuleUseCaseTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/install_test_' . uniqid();
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
    public function installFromDirectorySource(): void
    {
        $sourceDir = $this->createSourceModule('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute($sourceDir);

        $this->assertSame('blog', $result->name);
        $this->assertTrue($result->enabled);
        $this->assertSame('directory', $result->sourceType);
        $this->assertDirectoryExists($result->path);
        $this->assertFileExists($result->path . '/module.json');
    }

    #[Test]
    public function installFromZipSource(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute($zipPath);

        $this->assertSame('blog', $result->name);
        $this->assertSame('zip', $result->sourceType);
        $this->assertDirectoryExists($result->path);
    }

    #[Test]
    public function installDisabled(): void
    {
        $sourceDir = $this->createSourceModule('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute($sourceDir, disabled: true);

        $this->assertFalse($result->enabled);
        $manifest = json_decode(file_get_contents($result->path . '/module.json'), true);
        $this->assertFalse($manifest['state']['enabled']);
    }

    #[Test]
    public function installThrowsWhenModuleAlreadyInRegistry(): void
    {
        $sourceDir = $this->createSourceModule('blog');
        $this->createInstalledModule('blog');
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleAlreadyExistsException::class);
        $useCase->execute($sourceDir);
    }

    #[Test]
    public function installCleansUpPreparedSourceOnSuccess(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $useCase = $this->makeUseCase();

        $useCase->execute($zipPath);

        $tempDirs = glob(sys_get_temp_dir() . '/module_zip_*');
        $recentTempDirs = array_filter($tempDirs, fn ($d) => is_dir($d) && filemtime($d) > time() - 5);
        $this->assertEmpty($recentTempDirs);
    }

    private function makeUseCase(): InstallModuleUseCase
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules'], 'backup' => $this->tempDir . '/backups']],
        ]);

        $namespaceResolver = new FakeNamespaceResolver($this->tempDir);

        $manifests = new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: $namespaceResolver,
            documentReader: new ManifestDocumentReader(),
        );

        $sorter = new TopologicalSorter();
        $cache = new ModuleRegistryCache($validator, $layout, $this->tempDir);

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
        $paths = new ModuleLifecyclePaths($config, $this->tempDir, $this->tempDir . '/app');
        $directoryOps = new ModuleDirectoryOperations(new Filesystem(), $paths);
        $sourcePreparer = new ModuleSourcePreparer(
            new ManifestDocumentReader(),
            $validator,
            new ZipExtractor(),
        );

        return new InstallModuleUseCase(
            $registry,
            $manifests,
            $sourcePreparer,
            $paths,
            $guard,
            $directoryOps,
            $invalidator,
            $namespaceResolver,
        );
    }

    private function createSourceModule(string $name): string
    {
        $dir = $this->tempDir . '/sources/' . ucfirst($name);
        $this->filesystem->makeDirectory($dir, 0755, true);

        file_put_contents($dir . '/module.json', json_encode([
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'version' => '1.0.0'],
            'state' => ['enabled' => true],
            'settings' => ['schema' => new \stdClass(), 'values' => new \stdClass()],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        file_put_contents($dir . '/readme.txt', 'Module ' . $name);

        return $dir;
    }

    private function createSourceZip(string $name): string
    {
        $manifest = json_encode([
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'version' => '1.0.0'],
            'state' => ['enabled' => true],
            'settings' => ['schema' => new \stdClass(), 'values' => new \stdClass()],
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
        $path = $this->tempDir . '/app/Modules/' . ucfirst($name);
        $this->filesystem->makeDirectory($path, 0755, true);

        file_put_contents($path . '/module.json', json_encode([
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'version' => '1.0.0'],
            'state' => ['enabled' => true],
            'settings' => ['schema' => new \stdClass(), 'values' => new \stdClass()],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
