<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Registry;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryCacheTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-cache-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_builds_payload_without_settings_values(): void
    {
        $cache = $this->cache();
        $module = ModuleFactory::make(name: 'blog');

        $payload = $cache->buildPayload([$module]);

        self::assertSame(2, $payload['version']);
        self::assertSame(['blog'], $payload['load_order']);
        self::assertArrayHasKey('blog', $payload['modules']);
        self::assertArrayNotHasKey('values', $payload['modules']['blog']['manifest']['settings']);
    }

    #[Test]
    public function it_reports_existence_correctly(): void
    {
        $cache = $this->cache();

        self::assertFalse($cache->exists());

        file_put_contents($cache->cachePath(), '<?php return [];');

        self::assertTrue($cache->exists());
    }

    #[Test]
    public function it_forgets_cache_file(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return [];');

        $cache->forget();

        self::assertFileDoesNotExist($cache->cachePath());
    }

    #[Test]
    public function forget_is_idempotent(): void
    {
        $cache = $this->cache();

        $cache->forget();

        self::assertFileDoesNotExist($cache->cachePath());
    }

    #[Test]
    public function it_throws_for_wrong_cache_version(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return ' . var_export([
            'version' => 99,
            'modules' => [],
            'load_order' => [],
        ], true) . ';');

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('version is not supported');

        $cache->load();
    }

    #[Test]
    public function it_throws_when_load_order_references_missing_module(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return ' . var_export([
            'version' => 2,
            'modules' => [],
            'load_order' => ['missing'],
        ], true) . ';');

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('references missing module [missing]');

        $cache->load();
    }

    private function cache(): ModuleRegistryCache
    {
        return new ModuleRegistryCache(
            validator: new ManifestValidator(),
            layout: new ModuleLayout(),
            basePath: $this->tempDir,
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
