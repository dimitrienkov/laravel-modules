<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\NamespaceResolutionException;
use DimitrienkoV\LaravelModules\Support\ApplicationNamespaceResolver;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApplicationNamespaceResolver::class)]
#[Group('support')]
final class ApplicationNamespaceResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('resolver');
        $this->createDirectory($this->tempDir . '/app/Modules/Blog');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function resolvesModuleNamespaceFromAppPath(): void
    {
        $resolver = new ApplicationNamespaceResolver(
            $this->fakeApp($this->tempDir . '/app', 'App\\'),
        );

        $namespace = $resolver->resolve($this->tempDir . '/app/Modules/Blog');

        self::assertSame('App\\Modules\\Blog', $namespace);
    }

    #[Test]
    public function resolvesNestedModulePath(): void
    {
        $this->createDirectory($this->tempDir . '/app/Integrations/Shopify');

        $resolver = new ApplicationNamespaceResolver(
            $this->fakeApp($this->tempDir . '/app', 'App\\'),
        );

        $namespace = $resolver->resolve($this->tempDir . '/app/Integrations/Shopify');

        self::assertSame('App\\Integrations\\Shopify', $namespace);
    }

    #[Test]
    public function resolvesWithCustomAppPath(): void
    {
        $customPath = $this->tempDir . '/custom';
        $this->createDirectory($customPath . '/Modules/Blog');

        $resolver = new ApplicationNamespaceResolver(
            $this->fakeApp($customPath, 'Custom\\'),
        );

        $namespace = $resolver->resolve($customPath . '/Modules/Blog');

        self::assertSame('Custom\\Modules\\Blog', $namespace);
    }

    #[Test]
    public function throwsWhenPathIsOutsideAppPath(): void
    {
        $resolver = new ApplicationNamespaceResolver(
            $this->fakeApp($this->tempDir . '/app', 'App\\'),
        );

        $this->expectException(NamespaceResolutionException::class);
        $this->expectExceptionMessage('outside the application path');

        $resolver->resolve($this->tempDir . '/vendor/some/package');
    }

    #[Test]
    public function cachesAppPathAcrossCalls(): void
    {
        $app = $this->fakeApp($this->tempDir . '/app', 'App\\');

        $resolver = new ApplicationNamespaceResolver($app);
        $first = $resolver->resolve($this->tempDir . '/app/Modules/Blog');

        $this->createDirectory($this->tempDir . '/app/Modules/Shop');
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
}
