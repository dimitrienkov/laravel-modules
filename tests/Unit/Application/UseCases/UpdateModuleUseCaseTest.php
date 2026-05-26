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
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdateModuleUseCaseTest extends TestCase
{
    use CreatesModuleFiles;
    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/update_test_' . uniqid();
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
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

        $state = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertFalse($state['enabled']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $state['installed_at']);
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

        $state = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertFalse($state['settings']['values']['enable_comments']);
        $this->assertSame(50, $state['settings']['values']['max_posts']);
        $this->assertEmpty($result->skippedValues);
    }

    #[Test]
    public function skippedValuesHaveReasons(): void
    {
        $oldSchema = [
            'enable_comments' => ['type' => 'bool', 'default' => true],
            'old_feature' => ['type' => 'bool', 'default' => false],
            'bad_value' => ['type' => 'int', 'default' => 1, 'min' => 1, 'max' => 10],
        ];
        $oldValues = ['enable_comments' => false, 'old_feature' => true, 'bad_value' => 5];
        $newSchema = [
            'enable_comments' => ['type' => 'bool', 'default' => true],
            'bad_value' => ['type' => 'int', 'default' => 1, 'min' => 1, 'max' => 3],
        ];

        $this->createInstalledModule('blog', '1.0.0', schema: $oldSchema, values: $oldValues);
        $sourceDir = $this->createSourceModule('blog', '2.0.0', schema: $newSchema);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog', $sourceDir);

        $removedKey = null;
        $invalidKey = null;
        foreach ($result->skippedValues as $skipped) {
            if ($skipped->key === 'old_feature') {
                $removedKey = $skipped;
            }
            if ($skipped->key === 'bad_value') {
                $invalidKey = $skipped;
            }
        }

        $this->assertNotNull($removedKey);
        $this->assertSame('removed from schema', $removedKey->reason);

        $this->assertNotNull($invalidKey);
        $this->assertStringContainsString('invalid value', $invalidKey->reason);
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

        $skippedKeys = array_map(fn ($s) => $s->key, $result->skippedValues);
        $this->assertContains('old_feature', $skippedKeys);
    }

    private function makeUseCase(): UpdateModuleUseCase
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules'], 'backup' => $this->tempDir . '/backups', 'state' => $this->stateRoot]],
        ]);

        $stateRepo = new ModuleStateRepository(
            paths: new ModuleStatePaths(config: $config, basePath: $this->tempDir),
            writer: new AtomicJsonWriter(),
        );

        $manifests = new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
            documentReader: new ManifestDocumentReader(),
            stateRepository: $stateRepo,
        );

        $sorter = new TopologicalSorter();
        $cache = new ModuleRegistryCache($validator, $layout, $stateRepo, $this->tempDir);

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
            $registry,
            $manifests,
            $stateRepo,
            $sourcePreparer,
            $guard,
            $directoryOps,
            $invalidator,
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
        $this->writeModuleManifest(
            $this->tempDir . '/app/Modules',
            $name,
            $version,
            schema: $schema ?: new \stdClass(),
        );
        $this->writeModuleState($this->stateRoot, $name, $enabled, $installedAt, $values ?: new \stdClass());
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
            'settings' => [
                'schema' => $schema ?: new \stdClass(),
            ],
        ];

        file_put_contents($dir . '/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $dir;
    }
}
