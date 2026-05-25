<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\FeatureRepository;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FeatureRepositoryScopedBindingTest extends TestCase
{
    private string $modulePath;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-scoped-features-' . bin2hex(random_bytes(6));
        $this->modulePath = $this->tempDir . '/app/Modules/Blog';

        mkdir($this->modulePath, 0755, true);
        $this->writeManifest(['comments_enabled' => false]);
        $this->bindFeatureServices();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function scoped_feature_repository_sees_manifest_changes_on_next_scope(): void
    {
        $firstRequest = $this->application()->make(FeatureRepositoryInterface::class);

        self::assertFalse($firstRequest->bool('blog', 'comments_enabled'));

        $this->updateValues(['comments_enabled' => true]);

        self::assertFalse($firstRequest->bool('blog', 'comments_enabled'));

        $this->application()->forgetScopedInstances();
        $secondRequest = $this->application()->make(FeatureRepositoryInterface::class);

        self::assertTrue($secondRequest->bool('blog', 'comments_enabled'));
    }

    private function bindFeatureServices(): void
    {
        $app = $this->application();
        $layout = new ModuleLayout();
        $validator = new ManifestValidator();

        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                ],
            ],
        ]);

        $app->instance(ModuleManifestRepositoryInterface::class, new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
        ));

        $app->instance(ModuleRegistryInterface::class, new ModuleRegistry(
            manifests: $app->make(ModuleManifestRepositoryInterface::class),
            sorter: new TopologicalSorter(),
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new Filesystem(),
                layout: $layout,
                basePath: $this->tempDir,
            ),
            cache: new ModuleRegistryCache(
                validator: $validator,
                layout: $layout,
                basePath: $this->tempDir,
            ),
        ));

        $app->scoped(FeatureRepositoryInterface::class, FeatureRepository::class);
    }

    /**
     * @param array<string, bool|int|string> $values
     */
    private function updateValues(array $values): void
    {
        $repository = $this->application()->make(ModuleManifestRepositoryInterface::class);
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
                            'default' => false,
                        ],
                    ],
                    'values' => $values,
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    private function application(): Application
    {
        if ($this->app === null) {
            self::fail('Testbench application is not initialized.');
        }

        return $this->app;
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
