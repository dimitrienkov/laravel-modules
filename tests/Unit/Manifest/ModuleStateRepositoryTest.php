<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleStateException;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Manifest\VO\Checksum;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleOrigin;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleStateDocument;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleStateRepository::class)]
#[Group('manifest')]
final class ModuleStateRepositoryTest extends TestCase
{
    use CreatesModuleFiles;

    private const string VALID_CHECKSUM = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    private ModuleStateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/state_repo_test_' . uniqid();
        $this->stateRoot = $this->tempDir . '/state';
        $this->filesystem->makeDirectory($this->stateRoot, 0755, true);

        $config = new Repository([
            'modules' => ['paths' => ['state' => $this->stateRoot, 'directories' => ['app/Modules']]],
        ]);

        $this->repository = new ModuleStateRepository(
            paths: new ModuleStatePaths(config: $config, basePath: $this->tempDir),
            writer: new AtomicJsonWriter(),
            filesystem: new LocalFilesystem(new Filesystem()),
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function throwsOnScalarSettings(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "settings": "invalid"}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/settings must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnListSettings(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "settings": ["a", "b"]}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/settings must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnScalarSettingsValues(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "settings": {"values": 42}}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/settings\.values must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnListSettingsValues(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "settings": {"values": [1, 2, 3]}}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/settings\.values must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnIntegerKeyedStateObject(): void
    {
        // JSON {"1": ...} decodes to a non-list array with an integer key.
        $this->writeRawState($this->stateRoot, 'blog', '{"1": "enabled"}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/state file must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnIntegerKeyedSettingsValues(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "settings": {"values": {"1": "x"}}}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/settings\.values must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function throwsOnUnknownSettingsKey(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "settings": {"values": {}, "extra": "bad"}}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/unknown key \[extra\]/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function acceptsEmptySettings(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "settings": {}}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertTrue($doc->state->enabled);
        $this->assertSame([], $doc->values->toArray());
    }

    #[Test]
    public function acceptsMissingSettings(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertTrue($doc->state->enabled);
        $this->assertSame([], $doc->values->toArray());
    }

    #[Test]
    public function acceptsNullSettings(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "settings": null}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertTrue($doc->state->enabled);
    }

    #[Test]
    public function acceptsNullSettingsValues(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "settings": {"values": null}}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertSame([], $doc->values->toArray());
    }

    #[Test]
    public function deleteChecksResult(): void
    {
        $stateDir = $this->stateRoot . '/blog';
        mkdir($stateDir, 0755, true);
        file_put_contents($stateDir . '/state.json', '{}');

        $this->repository->delete('blog');

        $this->assertDirectoryDoesNotExist($stateDir);
    }

    #[Test]
    public function writeDocumentCreatesStateFile(): void
    {
        $module = $this->makeModule('blog');
        $state = ModuleState::initialState();
        $values = new FeatureValues($module->features, []);
        $document = new ModuleStateDocument($state, $values);

        $this->repository->writeDocument('blog', $document);

        $stateFile = $this->stateRoot . '/blog/state.json';
        $this->assertFileExists($stateFile);
        $raw = json_decode(file_get_contents($stateFile), true);
        $this->assertTrue($raw['enabled']);
        $this->assertArrayHasKey('installed_at', $raw);
    }

    #[Test]
    public function writeStateUpdatesModuleState(): void
    {
        $module = $this->makeModule('blog');
        $state = ModuleState::initialState();
        $values = new FeatureValues($module->features, []);
        $this->repository->writeDocument('blog', new ModuleStateDocument($state, $values));

        $newState = $state->withEnabled(false);
        $updated = $this->repository->writeState($module, $newState);

        $this->assertFalse($updated->state->enabled);

        $stateFile = $this->stateRoot . '/blog/state.json';
        $raw = json_decode(file_get_contents($stateFile), true);
        $this->assertFalse($raw['enabled']);
    }

    #[Test]
    public function readValuesReturnsEmptyForMissingStateFile(): void
    {
        $module = $this->makeModule('blog');

        $values = $this->repository->readValues($module);

        $this->assertSame([], $values->toArray());
    }

    #[Test]
    public function moveToBackupCopiesAndDeletesState(): void
    {
        $module = $this->makeModule('blog');
        $state = ModuleState::initialState();
        $values = new FeatureValues($module->features, []);
        $this->repository->writeDocument('blog', new ModuleStateDocument($state, $values));

        $backupDir = $this->tempDir . '/backup_blog';
        mkdir($backupDir, 0755, true);
        $backupStatePath = $this->repository->moveToBackup('blog', $backupDir);

        $this->assertNotNull($backupStatePath);
        $this->assertFileExists($backupStatePath);
        $this->assertFalse($this->repository->exists('blog'));
    }

    #[Test]
    public function deleteSkipsWhenStateDirectoryDoesNotExist(): void
    {
        $this->repository->delete('nonexistent');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function existsReturnsFalseForMissingModule(): void
    {
        $this->assertFalse($this->repository->exists('nonexistent'));
    }

    #[Test]
    public function existsReturnsTrueForWrittenState(): void
    {
        $module = $this->makeModule('blog');
        $state = ModuleState::initialState();
        $values = new FeatureValues($module->features, []);
        $this->repository->writeDocument('blog', new ModuleStateDocument($state, $values));

        $this->assertTrue($this->repository->exists('blog'));
    }

    #[Test]
    public function readHydratesOriginFromSource(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "source": {"kind": "zip", "installed_version": "1.0.0", "checksum": "' . self::VALID_CHECKSUM . '"}}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertNotNull($doc->origin);
        $this->assertSame('zip', $doc->origin->kind->value);
        $this->assertSame('1.0.0', $doc->origin->installedVersion);
        $this->assertNotNull($doc->origin->checksum);
        $this->assertSame(self::VALID_CHECKSUM, $doc->origin->checksum->value);
    }

    #[Test]
    public function readThrowsWhenSourceIsList(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "source": ["zip"]}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/source must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function readThrowsWhenSourceIsScalar(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "source": "zip"}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/source must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function readThrowsWhenSourceHasIntegerKeys(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true, "source": {"1": "zip"}}');

        $this->expectException(InvalidModuleStateException::class);
        $this->expectExceptionMessageMatches('/source must be a JSON object/');

        $this->repository->read('blog', $this->makeModule('blog'));
    }

    #[Test]
    public function readReturnsNullOriginWhenSourceAbsent(): void
    {
        $this->writeRawState($this->stateRoot, 'blog', '{"enabled": true}');

        $doc = $this->repository->read('blog', $this->makeModule('blog'));

        $this->assertNull($doc->origin);
    }

    #[Test]
    public function writeDocumentPersistsOrigin(): void
    {
        $module = $this->makeModule('blog');
        $state = ModuleState::initialState();
        $values = new FeatureValues($module->features, []);
        $origin = ModuleOrigin::forZip('1.0.0', new Checksum(self::VALID_CHECKSUM));
        $document = new ModuleStateDocument($state, $values, $origin);

        $this->repository->writeDocument('blog', $document);

        $raw = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertArrayHasKey('source', $raw);
        $this->assertSame('zip', $raw['source']['kind']);
        $this->assertSame('1.0.0', $raw['source']['installed_version']);
        $this->assertSame(self::VALID_CHECKSUM, $raw['source']['checksum']);
    }

    #[Test]
    public function writeStatePreservesOrigin(): void
    {
        $module = $this->makeModule('blog');
        $state = ModuleState::initialState();
        $values = new FeatureValues($module->features, []);
        $origin = ModuleOrigin::forLocal('1.0.0');
        $this->repository->writeDocument('blog', new ModuleStateDocument($state, $values, $origin));

        $newState = $state->withEnabled(false);
        $this->repository->writeState($module, $newState);

        $raw = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertFalse($raw['enabled']);
        $this->assertArrayHasKey('source', $raw);
        $this->assertSame('local', $raw['source']['kind']);
    }

    #[Test]
    public function writeValuesPreservesOrigin(): void
    {
        $module = $this->makeModule('blog');
        $state = ModuleState::initialState();
        $values = new FeatureValues($module->features, []);
        $origin = ModuleOrigin::forZip('1.0.0', new Checksum(self::VALID_CHECKSUM));
        $this->repository->writeDocument('blog', new ModuleStateDocument($state, $values, $origin));

        $this->repository->writeValues($module, $values);

        $raw = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertArrayHasKey('source', $raw);
        $this->assertSame('zip', $raw['source']['kind']);
        $this->assertSame(self::VALID_CHECKSUM, $raw['source']['checksum']);
    }

    #[Test]
    public function writeDocumentOmitsSourceWhenOriginNull(): void
    {
        $module = $this->makeModule('blog');
        $state = ModuleState::initialState();
        $values = new FeatureValues($module->features, []);
        $document = new ModuleStateDocument($state, $values);

        $this->repository->writeDocument('blog', $document);

        $raw = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertArrayNotHasKey('source', $raw);
    }

    private function makeModule(string $name): Module
    {
        return new Module(
            name: $name,
            displayName: ucfirst($name),
            namespace: 'App\\Modules\\' . ucfirst($name),
            path: $this->tempDir . '/app/Modules/' . ucfirst($name),
            schemaVersion: ManifestValidator::CURRENT_SCHEMA_VERSION,
            meta: new ManifestMeta(
                name: $name,
                displayName: ucfirst($name),
                kind: ModuleKind::Module,
                version: '1.0.0',
                author: null,
                description: null,
                license: null,
                dependencies: new ModuleDependencies([]),
            ),
            state: ModuleState::defaultDisabled(),
            features: new FeatureSchema([]),
        );
    }
}
