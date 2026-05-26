<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
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
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase
{
    use CreatesModuleFiles;
    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-registry-' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_scans_configured_directories_and_sorts_by_dependencies(): void
    {
        mkdir($this->tempDir . '/app/Modules/Empty');
        $this->writeModule('Users', 'users', '1.2.0');
        $this->writeModule('Blog', 'blog', '1.0.0', ['users' => '^1.0']);

        $registry = $this->registry();

        self::assertSame(['users', 'blog'], array_map(
            static fn ($module): string => $module->name,
            $registry->all(),
        ));
        self::assertCount(2, $registry->all());
        self::assertSame('App\\Modules\\Blog', $registry->find('blog')->namespace);
    }

    #[Test]
    public function it_reads_v3_cache_when_present(): void
    {
        $cachePath = $this->tempDir . '/bootstrap/cache/modules.php';
        mkdir(\dirname($cachePath), 0755, true);
        file_put_contents($cachePath, '<?php return ' . var_export([
            'version' => 3,
            'modules' => [
                'cached' => [
                    'path' => $this->tempDir . '/missing/Cached',
                    'namespace' => 'App\\Cached',
                    'manifest' => $this->manifest('cached', '1.0.0'),
                ],
            ],
            'load_order' => ['cached'],
        ], true) . ';' . PHP_EOL);

        $registry = $this->registry();

        self::assertSame(['cached'], array_map(
            static fn ($module): string => $module->name,
            $registry->all(),
        ));
        self::assertSame('App\\Cached', $registry->find('cached')->namespace);
    }

    #[Test]
    public function it_ignores_legacy_provider_cache_file(): void
    {
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
        file_put_contents($this->tempDir . '/bootstrap/cache/modules-providers.php', '<?php return ["Legacy\\\\Provider"];');
        $this->writeModule('Users', 'users', '1.0.0');

        self::assertSame(['users'], array_map(
            static fn ($module): string => $module->name,
            $this->registry()->all(),
        ));
    }

    #[Test]
    public function it_throws_when_module_is_missing(): void
    {
        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage('Module [missing] was not found');

        $this->registry()->find('missing');
    }

    private function stateRepository(): ModuleStateRepository
    {
        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                    'state' => $this->stateRoot,
                ],
            ],
        ]);

        return new ModuleStateRepository(
            paths: new ModuleStatePaths(config: $config, basePath: $this->tempDir),
            writer: new AtomicJsonWriter(),
            filesystem: new LocalFilesystem(new Filesystem()),
        );
    }

    private function registry(): ModuleRegistry
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $stateRepo = $this->stateRepository();
        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                    'state' => $this->stateRoot,
                ],
            ],
        ]);

        $manifests = new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
            documentReader: new ManifestDocumentReader(),
            stateRepository: $stateRepo,
            filesystem: new LocalFilesystem(new Filesystem()),
        );

        return new ModuleRegistry(
            builder: new ModuleRegistrySnapshotBuilder(
                scanner: new ModuleDirectoryScanner(
                    config: $config,
                    filesystem: new LocalFilesystem(new Filesystem()),
                    layout: $layout,
                    basePath: $this->tempDir,
                    appPath: $this->tempDir . '/app',
                ),
                manifests: $manifests,
                sorter: new TopologicalSorter(),
            ),
            cache: new ModuleRegistryCache(
                validator: $validator,
                layout: $layout,
                stateRepository: $stateRepo,
                basePath: $this->tempDir,
            ),
        );
    }

    /**
     * @param array<string, string> $dependencies
     *
     * @return array<string, mixed>
     */
    private function manifest(string $name, string $version, array $dependencies = []): array
    {
        return [
            'meta' => [
                'name' => $name,
                'display_name' => ucfirst($name),
                'version' => $version,
                'dependencies' => $dependencies,
            ],
            'settings' => [
                'schema' => [],
            ],
        ];
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function writeModule(string $directory, string $name, string $version, array $dependencies = []): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, $version, $dependencies, schema: []);
        $this->writeModuleState($this->stateRoot, $name, values: new \stdClass());
    }

}
