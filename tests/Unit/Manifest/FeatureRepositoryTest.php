<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\FeatureNotFoundException;
use DimitrienkoV\LaravelModules\Exceptions\FeatureTypeMismatchException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\FeatureRepository;
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
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureRepository::class)]
#[Group('manifest')]
final class FeatureRepositoryTest extends TestCase
{
    use CreatesModuleFiles;
    private string $modulePath;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-features-' . bin2hex(random_bytes(6));
        $this->modulePath = $this->tempDir . '/app/Modules/Blog';
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';

        mkdir($this->modulePath, 0755, true);
        $this->writeManifest();
        $this->writeState(['comments_enabled' => false, 'posts_per_page' => 20]);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function readsExplicitValuesAndSchemaDefaults(): void
    {
        $features = $this->featureRepository();

        self::assertFalse($features->getBool('blog', 'comments_enabled'));
        self::assertSame(20, $features->getInt('blog', 'posts_per_page'));
        self::assertSame('light', $features->getString('blog', 'theme'));
        self::assertSame('light', $features->get('blog', 'theme'));
    }

    #[Test]
    public function throwsForMissingFeatureKeys(): void
    {
        $this->expectException(FeatureNotFoundException::class);
        $this->expectExceptionMessage('Feature [missing]');

        $this->featureRepository()->get('blog', 'missing');
    }

    #[Test]
    public function typedAccessorsThrowWhenSchemaTypeDoesNotMatch(): void
    {
        $this->expectException(FeatureTypeMismatchException::class);
        $this->expectExceptionMessage('must be [int]');

        $this->featureRepository()->getInt('blog', 'comments_enabled');
    }

    #[Test]
    public function keepsFeatureValuesCacheInsideRepositoryInstance(): void
    {
        $features = $this->featureRepository();

        self::assertFalse($features->getBool('blog', 'comments_enabled'));

        $this->writeState(['comments_enabled' => true, 'posts_per_page' => 20]);

        self::assertFalse($features->getBool('blog', 'comments_enabled'));
        self::assertTrue($this->featureRepository()->getBool('blog', 'comments_enabled'));
    }

    #[Test]
    public function stringReturnsStringValueForStringFeature(): void
    {
        $features = $this->featureRepository();

        self::assertSame('light', $features->getString('blog', 'theme'));
    }

    #[Test]
    public function stringThrowsFeatureTypeMismatchForNonString(): void
    {
        $this->expectException(FeatureTypeMismatchException::class);
        $this->expectExceptionMessage('must be [string]');

        $this->featureRepository()->getString('blog', 'comments_enabled');
    }

    #[Test]
    public function throwsModuleNotFoundForUnknownModule(): void
    {
        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage('[nonexistent]');

        $this->featureRepository()->get('nonexistent', 'any_key');
    }

    private function featureRepository(): FeatureRepository
    {
        return new FeatureRepository(
            registry: $this->registry(),
            stateRepository: $this->stateRepository(),
        );
    }

    private function stateRepository(): ModuleStateRepository
    {
        return new ModuleStateRepository(
            paths: new ModuleStatePaths(
                stateRoot: $this->stateRoot,
                directories: ['app/Modules'],
                basePath: $this->tempDir,
            ),
            writer: new AtomicJsonWriter(),
            filesystem: new LocalFilesystem(new Filesystem()),
        );
    }

    private function registry(): ModuleRegistry
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $stateRepo = $this->stateRepository();

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
                    directories: ['app/Modules'],
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

    private function writeManifest(): void
    {
        file_put_contents(
            $this->modulePath . '/module.json',
            json_encode([
                'schema_version' => 1,
                'meta' => [
                    'name' => 'blog',
                    'display_name' => 'Blog',
                    'kind' => 'module',
                    'version' => '1.0.0',
                    'dependencies' => [],
                ],
                'settings' => [
                    'schema' => [
                        'comments_enabled' => [
                            'type' => 'bool',
                            'default' => true,
                        ],
                        'posts_per_page' => [
                            'type' => 'int',
                            'default' => 10,
                            'min' => 1,
                            'max' => 50,
                        ],
                        'theme' => [
                            'type' => 'string',
                            'default' => 'light',
                        ],
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, bool|int|string> $values
     */
    private function writeState(array $values): void
    {
        $this->writeModuleState($this->stateRoot, 'blog', installedAt: '2026-05-25T00:00:00+00:00', values: $values);
    }

}
