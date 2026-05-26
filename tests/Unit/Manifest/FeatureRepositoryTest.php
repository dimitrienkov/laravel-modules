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
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_reads_explicit_values_and_schema_defaults(): void
    {
        $features = $this->featureRepository();

        self::assertFalse($features->getBool('blog', 'comments_enabled'));
        self::assertSame(20, $features->getInt('blog', 'posts_per_page'));
        self::assertSame('light', $features->getString('blog', 'theme'));
        self::assertSame('light', $features->get('blog', 'theme'));
    }

    #[Test]
    public function it_throws_for_missing_feature_keys(): void
    {
        $this->expectException(FeatureNotFoundException::class);
        $this->expectExceptionMessage('Feature [missing]');

        $this->featureRepository()->get('blog', 'missing');
    }

    #[Test]
    public function typed_accessors_throw_when_schema_type_does_not_match(): void
    {
        $this->expectException(FeatureTypeMismatchException::class);
        $this->expectExceptionMessage('must be [int]');

        $this->featureRepository()->getInt('blog', 'comments_enabled');
    }

    #[Test]
    public function it_keeps_feature_values_cache_inside_repository_instance(): void
    {
        $features = $this->featureRepository();

        self::assertFalse($features->getBool('blog', 'comments_enabled'));

        $this->writeState(['comments_enabled' => true, 'posts_per_page' => 20]);

        self::assertFalse($features->getBool('blog', 'comments_enabled'));
        self::assertTrue($this->featureRepository()->getBool('blog', 'comments_enabled'));
    }

    #[Test]
    public function string_returns_string_value_for_string_feature(): void
    {
        $features = $this->featureRepository();

        self::assertSame('light', $features->getString('blog', 'theme'));
    }

    #[Test]
    public function string_throws_feature_type_mismatch_for_non_string(): void
    {
        $this->expectException(FeatureTypeMismatchException::class);
        $this->expectExceptionMessage('must be [string]');

        $this->featureRepository()->getString('blog', 'comments_enabled');
    }

    #[Test]
    public function it_throws_module_not_found_for_unknown_module(): void
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
        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                    'state' => $this->stateRoot,
                ],
            ],
        ]);

        return new ModuleStateRepository(
            paths: new ModuleStatePaths(
                config: $config,
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
        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                    'state' => $this->stateRoot,
                ],
            ],
        ]);

        return new ModuleRegistry(
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
                'meta' => [
                    'name' => 'blog',
                    'display_name' => 'Blog',
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

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}
