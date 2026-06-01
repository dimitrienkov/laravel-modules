<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\UseCases\UpdateModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleUpdateException;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdateModuleUseCaseTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;

    private const string VALID_CHECKSUM = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

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
        $sourceDir = $this->createSourceZip('blog', '2.0.0');
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
        $sourceDir = $this->createSourceZip('forum', '2.0.0');
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleUpdateException::class);
        $this->expectExceptionMessageMatches('/mismatch/');

        $useCase->execute('blog', $sourceDir);
    }

    #[Test]
    public function preservesStateFromTarget(): void
    {
        $this->createInstalledModule('blog', '1.0.0', enabled: false, installedAt: '2026-01-01T00:00:00+00:00');
        $sourceDir = $this->createSourceZip('blog', '2.0.0');
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
        $sourceDir = $this->createSourceZip('blog', '2.0.0', schema: $schema);
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
        $sourceDir = $this->createSourceZip('blog', '2.0.0', schema: $newSchema);
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
        $sourceDir = $this->createSourceZip('blog', '2.0.0', schema: $newSchema);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog', $sourceDir);

        $skippedKeys = array_map(fn ($s) => $s->key, $result->skippedValues);
        $this->assertContains('old_feature', $skippedKeys);
    }

    #[Test]
    public function rollbackOnManifestWriteFailure(): void
    {
        $this->createInstalledModule('blog', '1.0.0');
        $sourceDir = $this->createSourceZip('blog', '2.0.0');

        $originalManifest = json_decode(file_get_contents($this->tempDir . '/app/Modules/Blog/module.json'), true);
        $originalState = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);

        $failingManifests = $this->createMock(\DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface::class);
        $failingManifests->method('load')->willReturnCallback(
            fn (string $path) => $this->makeRealManifestRepo()->load($path),
        );
        $failingManifests->method('writeManifest')->willThrowException(
            new \DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException('simulated write failure'),
        );

        $useCase = $this->makeUseCase(manifestRepository: $failingManifests);

        try {
            $useCase->execute('blog', $sourceDir);
            $this->fail('Expected ModuleUpdateException');
        } catch (ModuleUpdateException $e) {
            $this->assertStringContainsString('restored from backup', $e->getMessage());
            $this->assertInstanceOf(\DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException::class, $e->getPrevious());
        }

        $restoredManifest = json_decode(file_get_contents($this->tempDir . '/app/Modules/Blog/module.json'), true);
        $restoredState = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertSame($originalManifest, $restoredManifest);
        $this->assertSame($originalState['enabled'], $restoredState['enabled']);
    }

    #[Test]
    public function doubleFaultPersistAndRestoreFailure(): void
    {
        $this->createInstalledModule('blog', '1.0.0');
        $sourceDir = $this->createSourceZip('blog', '2.0.0');

        $failingManifests = $this->createMock(\DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface::class);
        $failingManifests->method('load')->willReturnCallback(
            fn (string $path) => $this->makeRealManifestRepo()->load($path),
        );
        $failingManifests->method('writeManifest')->willReturnCallback(function (): void {
            $backupDirs = glob($this->tempDir . '/backups/blog-*');
            foreach ($backupDirs as $dir) {
                $this->filesystem->deleteDirectory($dir);
            }

            throw new \DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException('simulated write failure');
        });

        $useCase = $this->makeUseCase(manifestRepository: $failingManifests);

        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $useCase->execute('blog', $sourceDir);
            $this->fail('Expected ModuleUpdateException');
        } catch (ModuleUpdateException $e) {
            $this->assertStringContainsString('restore also failed', $e->getMessage());
            $this->assertStringContainsString('Backup remains at', $e->getMessage());
            $this->assertInstanceOf(\DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException::class, $e->getPrevious());
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function updateUpdatesOriginInstalledVersion(): void
    {
        $this->createInstalledModule('blog', '1.0.0', source: ['kind' => 'zip', 'installed_version' => '1.0.0', 'checksum' => self::VALID_CHECKSUM]);
        $zipPath = $this->createSourceZip('blog', '2.0.0');
        $useCase = $this->makeUseCase();

        $useCase->execute('blog', $zipPath);

        $state = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertArrayHasKey('source', $state);
        $this->assertSame('zip', $state['source']['kind']);
        $this->assertSame('2.0.0', $state['source']['installed_version']);
        $this->assertSame(hash_file('sha256', $zipPath), $state['source']['checksum']);
    }

    #[Test]
    public function updateWritesArchiveProvenanceWhenSourceAbsent(): void
    {
        $this->createInstalledModule('blog', '1.0.0');
        $zipPath = $this->createSourceZip('blog', '2.0.0');
        $useCase = $this->makeUseCase();

        $useCase->execute('blog', $zipPath);

        $state = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertArrayHasKey('source', $state);
        $this->assertSame('zip', $state['source']['kind']);
        $this->assertSame('2.0.0', $state['source']['installed_version']);
        $this->assertSame(hash_file('sha256', $zipPath), $state['source']['checksum']);
    }

    #[Test]
    public function updateRewritesLocalOriginToZip(): void
    {
        $this->createInstalledModule('blog', '1.0.0', source: ['kind' => 'local', 'installed_version' => '1.0.0']);
        $zipPath = $this->createSourceZip('blog', '2.0.0');
        $useCase = $this->makeUseCase();

        $useCase->execute('blog', $zipPath);

        $state = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertSame('zip', $state['source']['kind']);
        $this->assertSame('2.0.0', $state['source']['installed_version']);
        $this->assertSame(hash_file('sha256', $zipPath), $state['source']['checksum']);
    }

    #[Test]
    public function successfulUpdateCleansUpBackup(): void
    {
        $this->createInstalledModule('blog', '1.0.0');
        $sourceDir = $this->createSourceZip('blog', '2.0.0');
        $useCase = $this->makeUseCase();

        $useCase->execute('blog', $sourceDir);

        $backupDirs = glob($this->tempDir . '/backups/blog-*');
        $this->assertEmpty($backupDirs, 'Backup directory should be cleaned up after successful update');
    }

    private function makeUseCase(
        ?\DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface $manifestRepository = null,
    ): UpdateModuleUseCase {
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        return new UpdateModuleUseCase(
            $registry,
            $manifestRepository ?? $manifests,
            $stateRepo,
            $this->lifecycleSourcePreparer(),
            $this->lifecycleDependencyGuard($registry),
            $this->lifecycleDirectoryOps($this->lifecycleDirectoryPaths($config)),
            $this->lifecycleInvalidator($cache, $registry),
        );
    }

    private function makeRealManifestRepo(): \DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository
    {
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');

        return $this->lifecycleManifestRepository($this->lifecycleStateRepository($config));
    }

    /**
     * @param array<string, mixed>      $schema
     * @param array<string, mixed>      $values
     * @param array<string, mixed>|null $source
     */
    private function createInstalledModule(
        string $name,
        string $version,
        bool $enabled = true,
        ?string $installedAt = null,
        array $schema = [],
        array $values = [],
        ?array $source = null,
    ): void {
        $this->writeModuleManifest(
            $this->tempDir . '/app/Modules',
            $name,
            $version,
            schema: $schema ?: new \stdClass(),
        );
        $this->writeModuleState($this->stateRoot, $name, $enabled, $installedAt, $values ?: new \stdClass(), $source);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function createSourceZip(string $name, string $version, array $schema = []): string
    {
        $manifest = json_encode([
            'schema_version' => 1,
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'kind' => 'module', 'version' => $version],
            'settings' => [
                'schema' => $schema ?: new \stdClass(),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $zipPath = $this->tempDir . '/sources/' . $name . '-' . $version . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('module.json', $manifest);
        $zip->close();

        return $zipPath;
    }
}
