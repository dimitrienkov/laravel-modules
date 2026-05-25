<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleLifecyclePaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Application\UseCases\UpdateModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleUpdateException;
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

final class UpdateModuleUseCaseTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/update_test_' . uniqid();
        $this->filesystem->makeDirectory($this->tempDir . '/bootstrap/cache', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/app/Modules', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/backups', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/sources', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function updatesModuleSuccessfully(): void
    {
        $this->createInstalledModule('blog', '1.0.0');
        $sourceDir = $this->createSourceModule('blog', '2.0.0');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog', $sourceDir);

        $this->assertSame('1.0.0', $result->oldVersion);
        $this->assertSame('2.0.0', $result->newVersion);
        $this->assertSame('blog', $result->name);
    }

    #[Test]
    public function throwsOnNameMismatch(): void
    {
        $this->createInstalledModule('blog', '1.0.0');
        $sourceDir = $this->createSourceModule('forum', '2.0.0');
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleUpdateException::class);
        $this->expectExceptionMessageMatches('/mismatch/');

        $useCase->execute('blog', $sourceDir);
    }

    #[Test]
    public function preservesStateFromTarget(): void
    {
        $this->createInstalledModule('blog', '1.0.0', enabled: false, installedAt: '2026-01-01T00:00:00+00:00');
        $sourceDir = $this->createSourceModule('blog', '2.0.0');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog', $sourceDir);

        $manifest = json_decode(file_get_contents($this->tempDir . '/app/Modules/Blog/module.json'), true);
        $this->assertFalse($manifest['state']['enabled']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $manifest['state']['installed_at']);
    }

    #[Test]
    public function preservesValidExplicitValues(): void
    {
        $schema = [
            'enable_comments' => ['type' => 'bool', 'default' => true],
            'max_posts' => ['type' => 'int', 'default' => 20, 'min' => 1, 'max' => 100],
        ];
        $values = ['enable_comments' => false, 'max_posts' => 50];

        $this->createInstalledModule('blog', '1.0.0', schema: $schema, values: $values);
        $sourceDir = $this->createSourceModule('blog', '2.0.0', schema: $schema);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog', $sourceDir);

        $manifest = json_decode(file_get_contents($this->tempDir . '/app/Modules/Blog/module.json'), true);
        $this->assertFalse($manifest['settings']['values']['enable_comments']);
        $this->assertSame(50, $manifest['settings']['values']['max_posts']);
        $this->assertEmpty($result->skippedValues);
    }

    #[Test]
    public function skipsRemovedSchemaKeys(): void
    {
        $oldSchema = [
            'enable_comments' => ['type' => 'bool', 'default' => true],
            'old_feature' => ['type' => 'bool', 'default' => false],
        ];
        $oldValues = ['enable_comments' => false, 'old_feature' => true];
        $newSchema = ['enable_comments' => ['type' => 'bool', 'default' => true]];

        $this->createInstalledModule('blog', '1.0.0', schema: $oldSchema, values: $oldValues);
        $sourceDir = $this->createSourceModule('blog', '2.0.0', schema: $newSchema);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog', $sourceDir);

        $this->assertContains('old_feature', $result->skippedValues);
    }

    private function makeUseCase(): UpdateModuleUseCase
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules'], 'backup' => $this->tempDir . '/backups']],
        ]);

        $manifests = new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
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
        $sourcePreparer = new ModuleSourcePreparer(new ManifestDocumentReader(), $validator, new ZipExtractor());

        return new UpdateModuleUseCase(
            $registry, $manifests, $sourcePreparer, $guard, $directoryOps, $invalidator,
        );
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $values
     */
    private function createInstalledModule(
        string $name,
        string $version,
        bool $enabled = true,
        ?string $installedAt = null,
        array $schema = [],
        array $values = [],
    ): void {
        $path = $this->tempDir . '/app/Modules/' . ucfirst($name);
        $this->filesystem->makeDirectory($path, 0755, true);

        $manifest = [
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'version' => $version],
            'state' => ['enabled' => $enabled],
            'settings' => [
                'schema' => $schema ?: new \stdClass(),
                'values' => $values ?: new \stdClass(),
            ],
        ];
        if ($installedAt !== null) {
            $manifest['state']['installed_at'] = $installedAt;
        }

        file_put_contents($path . '/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function createSourceModule(string $name, string $version, array $schema = []): string
    {
        $dir = $this->tempDir . '/sources/' . ucfirst($name);
        if (is_dir($dir)) {
            $this->filesystem->deleteDirectory($dir);
        }
        $this->filesystem->makeDirectory($dir, 0755, true);

        $manifest = [
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'version' => $version],
            'state' => ['enabled' => true],
            'settings' => [
                'schema' => $schema ?: new \stdClass(),
                'values' => new \stdClass(),
            ],
        ];

        file_put_contents($dir . '/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $dir;
    }
}
