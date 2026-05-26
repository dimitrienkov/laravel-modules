<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LifecycleRegistryInvalidatorTest extends TestCase
{
    use CreatesModuleFiles;
    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/lifecycle_invalidator_' . uniqid();
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
    public function invalidateForgetsCacheFileAndResetsRegistry(): void
    {
        $cachePath = $this->tempDir . '/bootstrap/cache/modules.php';
        file_put_contents($cachePath, '<?php return [];');
        $this->assertFileExists($cachePath);

        [$cache, $registry] = $this->createServices();
        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        $invalidator->flushAndReset();

        $this->assertFileDoesNotExist($cachePath);
    }

    #[Test]
    public function invalidateSucceedsWhenCacheDoesNotExist(): void
    {
        $cachePath = $this->tempDir . '/bootstrap/cache/modules.php';
        $this->assertFileDoesNotExist($cachePath);

        [$cache, $registry] = $this->createServices();
        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        $invalidator->flushAndReset();

        $this->assertFileDoesNotExist($cachePath);
    }

    #[Test]
    public function invalidateResetsRegistryInMemoryState(): void
    {
        $this->createModuleDirectory('blog', ['name' => 'blog', 'version' => '1.0.0']);

        [$cache, $registry] = $this->createServices();

        $registry->all();
        $this->assertCount(1, $registry->all());

        $this->filesystem->deleteDirectory($this->tempDir . '/app/Modules/Blog');

        $registry->all();
        $this->assertCount(1, $registry->all());

        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);
        $invalidator->flushAndReset();

        $this->assertCount(0, $registry->all());
    }

    /**
     * @return array{0: ModuleRegistryCache, 1: ModuleRegistry}
     */
    private function createServices(): array
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

        $cache = new ModuleRegistryCache(
            validator: $validator,
            layout: $layout,
            stateRepository: $stateRepo,
            basePath: $this->tempDir,
        );

        $registry = new ModuleRegistry(
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
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new LocalFilesystem(new Filesystem()),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            cache: $cache,
        );

        return [$cache, $registry];
    }

    /**
     * @param array{name: string, version: string} $meta
     */
    private function createModuleDirectory(string $dirName, array $meta): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $meta['name'], $meta['version']);
        $this->writeModuleState($this->stateRoot, $meta['name'], values: new \stdClass());
    }
}
