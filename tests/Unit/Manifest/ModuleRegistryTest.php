<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ComposerNamespaceResolver;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-registry-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        $this->writeComposer();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_scans_configured_directories_and_sorts_by_dependencies(): void
    {
        mkdir($this->tempDir . '/app/Modules/Empty');
        $this->writeModule('Users', $this->manifest('users', '1.2.0'));
        $this->writeModule('Blog', $this->manifest('blog', '1.0.0', ['users' => '^1.0']));

        $registry = $this->registry();

        self::assertSame(['users', 'blog'], array_map(
            static fn ($module): string => $module->name,
            $registry->loadOrder(),
        ));
        self::assertCount(2, $registry->all());
        self::assertSame('App\\Modules\\Blog', $registry->find('blog')->namespace);
    }

    #[Test]
    public function it_reads_v2_cache_when_present(): void
    {
        $cachePath = $this->tempDir . '/bootstrap/cache/modules.php';
        mkdir(\dirname($cachePath), 0755, true);
        file_put_contents($cachePath, '<?php return ' . var_export([
            'version' => 2,
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
            $registry->loadOrder(),
        ));
        self::assertSame('App\\Cached', $registry->find('cached')->namespace);
    }

    #[Test]
    public function it_ignores_legacy_provider_cache_file(): void
    {
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
        file_put_contents($this->tempDir . '/bootstrap/cache/modules-providers.php', '<?php return ["Legacy\\\\Provider"];');
        $this->writeModule('Users', $this->manifest('users', '1.0.0'));

        self::assertSame(['users'], array_map(
            static fn ($module): string => $module->name,
            $this->registry()->loadOrder(),
        ));
    }

    #[Test]
    public function it_throws_when_module_is_missing(): void
    {
        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage('Module [missing] was not found');

        $this->registry()->find('missing');
    }

    #[Test]
    public function it_builds_v2_cache_payload_from_scanned_modules(): void
    {
        $this->writeModule('Users', $this->manifest('users', '1.0.0'));

        $payload = $this->registry()->buildCachePayload();

        self::assertSame(2, $payload['version']);
        self::assertSame(['users'], $payload['load_order']);
        self::assertArrayHasKey('users', $payload['modules']);
        self::assertSame('App\\Modules\\Users', $payload['modules']['users']['namespace']);
    }

    private function registry(): ModuleRegistry
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator();
        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                ],
            ],
        ]);

        return new ModuleRegistry(
            manifests: new ModuleManifestRepository(
                layout: $layout,
                writer: new AtomicJsonWriter(),
                validator: $validator,
                namespaceResolver: new ComposerNamespaceResolver($this->tempDir),
            ),
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
            'state' => [
                'enabled' => true,
            ],
            'settings' => [
                'schema' => [],
                'values' => [],
            ],
        ];
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

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeModule(string $directory, array $manifest): void
    {
        $modulePath = $this->tempDir . '/app/Modules/' . $directory;
        mkdir($modulePath, 0755, true);
        file_put_contents(
            $modulePath . '/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
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
