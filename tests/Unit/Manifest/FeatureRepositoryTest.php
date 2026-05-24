<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\FeatureNotFoundException;
use DimitrienkoV\LaravelModules\Exceptions\FeatureTypeMismatchException;
use DimitrienkoV\LaravelModules\Manifest\FeatureRepository;
use DimitrienkoV\LaravelModules\Manifest\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ComposerNamespaceResolver;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureRepositoryTest extends TestCase
{
    private string $modulePath;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-features-' . bin2hex(random_bytes(6));
        $this->modulePath = $this->tempDir . '/app/Modules/Blog';

        mkdir($this->modulePath, 0755, true);
        $this->writeComposer();
        $this->writeManifest(['comments_enabled' => false, 'posts_per_page' => 20]);
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

        self::assertFalse($features->bool('blog', 'comments_enabled'));
        self::assertSame(20, $features->int('blog', 'posts_per_page'));
        self::assertSame('light', $features->string('blog', 'theme'));
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

        $this->featureRepository()->int('blog', 'comments_enabled');
    }

    #[Test]
    public function it_keeps_feature_values_cache_inside_repository_instance(): void
    {
        $features = $this->featureRepository();

        self::assertFalse($features->bool('blog', 'comments_enabled'));

        $this->updateValues(['comments_enabled' => true, 'posts_per_page' => 20]);

        self::assertFalse($features->bool('blog', 'comments_enabled'));
        self::assertTrue($this->featureRepository()->bool('blog', 'comments_enabled'));
    }

    private function featureRepository(): FeatureRepository
    {
        return new FeatureRepository(
            registry: $this->registry(),
            manifests: $this->manifestRepository(),
        );
    }

    private function registry(): ModuleRegistry
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator();

        return new ModuleRegistry(
            config: new Repository([
                'modules' => [
                    'paths' => [
                        'directories' => ['app/Modules'],
                    ],
                ],
            ]),
            filesystem: new Filesystem(),
            manifests: $this->manifestRepository(),
            validator: $validator,
            sorter: new TopologicalSorter(),
            layout: $layout,
            basePath: $this->tempDir,
        );
    }

    private function manifestRepository(): ModuleManifestRepository
    {
        $layout = new ModuleLayout();

        return new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: new ManifestValidator(),
            namespaceResolver: new ComposerNamespaceResolver($this->tempDir),
        );
    }

    /**
     * @param array<string, bool|int|string> $values
     */
    private function updateValues(array $values): void
    {
        $repository = $this->manifestRepository();
        $module = $repository->load($this->modulePath);
        $repository->updateFeatureValues(
            $module,
            FeatureValues::fromArray($values, $module->features, $module->name, $module->manifestPath()),
        );
    }

    /**
     * @param array<string, bool|int|string> $values
     */
    private function writeManifest(array $values): void
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
                'state' => [
                    'enabled' => true,
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
                    'values' => $values,
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    private function writeComposer(): void
    {
        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode([
                'autoload' => [
                    'psr-4' => [
                        'App\\' => 'app/',
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
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
