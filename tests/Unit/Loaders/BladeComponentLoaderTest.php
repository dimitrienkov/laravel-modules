<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\BladeComponentLoader;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BladeComponentLoader::class)]
#[Group('loaders')]
final class BladeComponentLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('blade-component-loader');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function registersBladeComponentNamespaceForModule(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/View/Components', 0755, true);
        $blade = new BladeCompiler(new Filesystem(), $this->tempDir);
        $app = new Application($this->tempDir);
        $app->singleton(BladeCompiler::class, static fn (): BladeCompiler => $blade);

        $this->loader($app)
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertFalse($app->resolved(BladeCompiler::class));

        $app->make(BladeCompiler::class);

        $namespaces = $blade->getClassComponentNamespaces();
        self::assertArrayHasKey('blog', $namespaces);
        self::assertSame('App\\Modules\\Blog\\View\\Components', $namespaces['blog']);
    }

    #[Test]
    public function registersBladeComponentNamespaceWhenCompilerWasAlreadyResolved(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/View/Components', 0755, true);
        $blade = new BladeCompiler(new Filesystem(), $this->tempDir);
        $app = new Application($this->tempDir);
        $app->instance(BladeCompiler::class, $blade);
        $app->make(BladeCompiler::class);

        $this->loader($app)
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $namespaces = $blade->getClassComponentNamespaces();
        self::assertArrayHasKey('blog', $namespaces);
        self::assertSame('App\\Modules\\Blog\\View\\Components', $namespaces['blog']);
    }

    #[Test]
    public function returnsEarlyWhenBladeComponentsDirectoryIsMissing(): void
    {
        $blade = new BladeCompiler(new Filesystem(), $this->tempDir);
        $app = new Application($this->tempDir);
        $app->singleton(BladeCompiler::class, static fn (): BladeCompiler => $blade);

        $this->loader($app)
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        $app->make(BladeCompiler::class);

        self::assertSame([], $blade->getClassComponentNamespaces());
    }

    private function loader(Application $app): BladeComponentLoader
    {
        return new BladeComponentLoader(new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout());
    }
}
