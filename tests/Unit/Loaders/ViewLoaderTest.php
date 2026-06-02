<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\ViewLoader;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewLoader::class)]
#[Group('loaders')]
final class ViewLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('view-loader');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function registersViewNamespaceForModule(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $viewsDir = $modulePath . '/Resources/views';
        mkdir($viewsDir, 0755, true);
        file_put_contents($viewsDir . '/index.blade.php', '<h1>Blog</h1>');
        $finder = new FileViewFinder(new Filesystem(), []);
        $factory = new ViewFactory(new EngineResolver(), $finder, new Dispatcher());
        $app = new Application($this->tempDir);
        $app->singleton('view', static fn (): ViewFactory => $factory);

        $report = $this->loader($app)
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath));

        self::assertFalse($app->resolved('view'));

        $app->make('view');

        $hints = $finder->getHints();
        self::assertArrayHasKey('blog', $hints);
        self::assertSame([$viewsDir], $hints['blog']);
        self::assertTrue($report->wasApplied());
        self::assertSame(['views' => ['Resources/views']], $report->artifacts);
    }

    #[Test]
    public function registersViewNamespaceWhenViewFactoryWasAlreadyResolved(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $viewsDir = $modulePath . '/Resources/views';
        mkdir($viewsDir, 0755, true);
        $finder = new FileViewFinder(new Filesystem(), []);
        $factory = new ViewFactory(new EngineResolver(), $finder, new Dispatcher());
        $app = new Application($this->tempDir);
        $app->instance('view', $factory);
        $app->make('view');

        $this->loader($app)
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath));

        $hints = $finder->getHints();
        self::assertArrayHasKey('blog', $hints);
        self::assertSame([$viewsDir], $hints['blog']);
    }

    #[Test]
    public function returnsEarlyWhenViewsDirectoryIsMissing(): void
    {
        $finder = new FileViewFinder(new Filesystem(), []);
        $factory = new ViewFactory(new EngineResolver(), $finder, new Dispatcher());
        $app = new Application($this->tempDir);
        $app->singleton('view', static fn (): ViewFactory => $factory);

        $report = $this->loader($app)
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        $app->make('view');

        self::assertSame([], $finder->getHints());
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::NoDirectory, $report->reason);
    }

    #[Test]
    public function registersViewNamespacesForTwoModulesWithoutCollision(): void
    {
        $blogPath = $this->tempDir . '/Blog';
        $blogViewsDir = $blogPath . '/Resources/views';
        mkdir($blogViewsDir, 0755, true);

        $shopPath = $this->tempDir . '/Shop';
        $shopViewsDir = $shopPath . '/Resources/views';
        mkdir($shopViewsDir, 0755, true);

        $finder = new FileViewFinder(new Filesystem(), []);
        $factory = new ViewFactory(new EngineResolver(), $finder, new Dispatcher());
        $app = new Application($this->tempDir);
        $app->singleton('view', static fn (): ViewFactory => $factory);

        $loader = $this->loader($app);
        $loader->load(ModuleFactory::make(name: 'blog', path: $blogPath));
        $loader->load(ModuleFactory::make(name: 'shop', path: $shopPath));

        $app->make('view');

        $hints = $finder->getHints();
        self::assertArrayHasKey('blog', $hints);
        self::assertSame([$blogViewsDir], $hints['blog']);
        self::assertArrayHasKey('shop', $hints);
        self::assertSame([$shopViewsDir], $hints['shop']);
    }

    private function loader(Application $app): ViewLoader
    {
        return new ViewLoader(new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout());
    }
}
