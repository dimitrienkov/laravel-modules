<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\NamespaceResolutionException;
use DimitrienkoV\LaravelModules\Support\ApplicationNamespaceResolver;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationNamespaceResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-resolver-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/app/Modules/Blog', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_resolves_module_namespace_from_app_path(): void
    {
        $resolver = new ApplicationNamespaceResolver(
            $this->fakeApp($this->tempDir . '/app', 'App\\'),
        );

        $namespace = $resolver->resolve($this->tempDir . '/app/Modules/Blog');

        self::assertSame('App\\Modules\\Blog', $namespace);
    }

    #[Test]
    public function it_resolves_nested_module_path(): void
    {
        mkdir($this->tempDir . '/app/Integrations/Shopify', 0755, true);

        $resolver = new ApplicationNamespaceResolver(
            $this->fakeApp($this->tempDir . '/app', 'App\\'),
        );

        $namespace = $resolver->resolve($this->tempDir . '/app/Integrations/Shopify');

        self::assertSame('App\\Integrations\\Shopify', $namespace);
    }

    #[Test]
    public function it_resolves_with_custom_app_path(): void
    {
        $customPath = $this->tempDir . '/custom';
        mkdir($customPath . '/Modules/Blog', 0755, true);

        $resolver = new ApplicationNamespaceResolver(
            $this->fakeApp($customPath, 'Custom\\'),
        );

        $namespace = $resolver->resolve($customPath . '/Modules/Blog');

        self::assertSame('Custom\\Modules\\Blog', $namespace);
    }

    #[Test]
    public function it_throws_when_path_is_outside_app_path(): void
    {
        $resolver = new ApplicationNamespaceResolver(
            $this->fakeApp($this->tempDir . '/app', 'App\\'),
        );

        $this->expectException(NamespaceResolutionException::class);
        $this->expectExceptionMessage('outside the application path');

        $resolver->resolve($this->tempDir . '/vendor/some/package');
    }

    #[Test]
    public function it_caches_app_path_across_calls(): void
    {
        $app = $this->fakeApp($this->tempDir . '/app', 'App\\');

        $resolver = new ApplicationNamespaceResolver($app);
        $first = $resolver->resolve($this->tempDir . '/app/Modules/Blog');

        mkdir($this->tempDir . '/app/Modules/Shop', 0755, true);
        $second = $resolver->resolve($this->tempDir . '/app/Modules/Shop');

        self::assertSame('App\\Modules\\Blog', $first);
        self::assertSame('App\\Modules\\Shop', $second);
    }

    private function fakeApp(string $appPath, string $namespace): Application
    {
        /** @var Application&Mockery\MockInterface $app */
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('path')->andReturn($appPath);
        $app->shouldReceive('getNamespace')->andReturn($namespace);

        return $app;
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
