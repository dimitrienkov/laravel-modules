<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Registry;

use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidModuleCacheException;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleRegistryCache::class)]
#[Group('registry')]
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
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function buildsPayloadWithoutStateOrValues(): void
    {
        $cache = $this->cache();
        $module = ModuleFactory::make(name: 'blog');

        $payload = $cache->buildPayload([$module]);

        self::assertSame(4, $payload->version);
        self::assertSame(['blog'], $payload->loadOrder);
        self::assertArrayHasKey('blog', $payload->modules);
        self::assertArrayHasKey('schema_version', $payload->modules['blog']->manifest);
        self::assertSame(1, $payload->modules['blog']->manifest['schema_version']);
        self::assertArrayNotHasKey('state', $payload->modules['blog']->manifest);
        self::assertArrayNotHasKey('values', $payload->modules['blog']->manifest['settings']);
    }

    #[Test]
    public function writesAndReadsCacheRoundTrip(): void
    {
        $cache = $this->cache();
        $module = ModuleFactory::make(name: 'blog', path: $this->tempDir . '/app/Modules/Blog');
        mkdir($this->tempDir . '/app/Modules/Blog', 0755, true);
        file_put_contents($this->tempDir . '/app/Modules/Blog/module.json', '{}');

        $cachePath = $cache->write([$module]);

        self::assertFileExists($cachePath);
        self::assertFileDoesNotExist($cachePath . '.lock');
        self::assertTrue($cache->exists());
    }

    #[Test]
    public function preservesExistingCacheFilePermissionsOnAtomicWrite(): void
    {
        $cache = $this->cache();
        $cachePath = $cache->cachePath();
        file_put_contents($cachePath, '<?php return [];');
        chmod($cachePath, 0640);

        $cache->write([ModuleFactory::make(name: 'blog')]);

        self::assertSame(0640, fileperms($cachePath) & 0777);
    }

    #[Test]
    public function reportsExistenceCorrectly(): void
    {
        $cache = $this->cache();

        self::assertFalse($cache->exists());

        file_put_contents($cache->cachePath(), '<?php return [];');

        self::assertTrue($cache->exists());
    }

    #[Test]
    public function forgetsCacheFile(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return [];');

        $cache->forget();

        self::assertFileDoesNotExist($cache->cachePath());
    }

    #[Test]
    public function forgetIsIdempotent(): void
    {
        $cache = $this->cache();

        $cache->forget();

        self::assertFileDoesNotExist($cache->cachePath());
    }

    #[Test]
    public function rejectsV3CachePayload(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return ' . var_export([
            'version' => 3,
            'modules' => [],
            'load_order' => [],
        ], true) . ';');

        $this->expectException(InvalidModuleCacheException::class);
        $this->expectExceptionMessage('version is not supported');

        $cache->load();
    }

    #[Test]
    public function throwsForWrongCacheVersion(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return ' . var_export([
            'version' => 99,
            'modules' => [],
            'load_order' => [],
        ], true) . ';');

        $this->expectException(InvalidModuleCacheException::class);
        $this->expectExceptionMessage('version is not supported');

        $cache->load();
    }

    #[Test]
    public function wrapsCacheRequireFailures(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php throw new RuntimeException("broken cache");');

        $this->expectException(InvalidModuleCacheException::class);
        $this->expectExceptionMessage('cache file could not be loaded: broken cache');

        $cache->load();
    }

    #[Test]
    public function throwsWhenLoadOrderReferencesMissingModule(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return ' . var_export([
            'version' => 4,
            'modules' => [],
            'load_order' => ['missing'],
        ], true) . ';');

        $this->expectException(InvalidModuleCacheException::class);
        $this->expectExceptionMessage('references missing module [missing]');

        $cache->load();
    }

    #[Test]
    public function throwsWhenLoadOrderContainsDuplicates(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return ' . var_export([
            'version' => 4,
            'modules' => [
                'blog' => ['path' => '/tmp/blog', 'namespace' => 'App\\Blog', 'manifest' => []],
            ],
            'load_order' => ['blog', 'blog'],
        ], true) . ';');

        $this->expectException(InvalidModuleCacheException::class);
        $this->expectExceptionMessage('duplicate name [blog]');

        $cache->load();
    }

    #[Test]
    public function throwsWhenCachedManifestHasIntegerKeys(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return ' . var_export([
            'version' => 4,
            'modules' => [
                'blog' => ['path' => '/tmp/blog', 'namespace' => 'App\\Blog', 'manifest' => [1 => 'x']],
            ],
            'load_order' => ['blog'],
        ], true) . ';');

        $this->expectException(InvalidModuleCacheException::class);
        $this->expectExceptionMessage('manifest must be an object');

        $cache->load();
    }

    #[Test]
    public function throwsWhenModuleAbsentFromLoadOrder(): void
    {
        $cache = $this->cache();
        file_put_contents($cache->cachePath(), '<?php return ' . var_export([
            'version' => 4,
            'modules' => [
                'blog' => ['path' => '/tmp/blog', 'namespace' => 'App\\Blog', 'manifest' => []],
                'users' => ['path' => '/tmp/users', 'namespace' => 'App\\Users', 'manifest' => []],
            ],
            'load_order' => ['blog'],
        ], true) . ';');

        $this->expectException(InvalidModuleCacheException::class);
        $this->expectExceptionMessage('absent from load_order');

        $cache->load();
    }

    private function cache(): ModuleRegistryCache
    {
        $stateRepo = $this->createMock(ModuleStateRepositoryInterface::class);
        $stateRepo->method('readState')->willReturn(ModuleState::defaultDisabled());

        return new ModuleRegistryCache(
            validator: new ManifestValidator(new ManifestSettingsValidator()),
            layout: new ModuleLayout(),
            stateRepository: $stateRepo,
            basePath: $this->tempDir,
        );
    }

}
