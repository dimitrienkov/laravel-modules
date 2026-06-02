<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\ConsoleRouteLoader;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\Stubs\RecordingConsoleKernel;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ConsoleRouteLoader::class)]
#[Group('loaders')]
final class ConsoleRouteLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('console-route-loader');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function registersConsoleRouteFileViaKernelAfterBoot(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $consoleRoutesFile = $modulePath . '/Routes/console.php';
        mkdir(\dirname($consoleRoutesFile), 0755, true);
        file_put_contents($consoleRoutesFile, '<?php // console routes');
        $app = new Application($this->tempDir);
        $kernel = new RecordingConsoleKernel();

        $report = $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath));

        $app->singleton(ConsoleKernelContract::class, static fn(): RecordingConsoleKernel => $kernel);
        $app->make(ConsoleKernelContract::class);

        self::assertSame([], $kernel->addedRoutePaths);

        $app->boot();

        self::assertContains($consoleRoutesFile, $kernel->addedRoutePaths);
        self::assertTrue($report->wasApplied());
        self::assertSame(['console' => ['console.php']], $report->artifacts);
    }

    #[Test]
    public function registersConsoleRouteFileWhenContractKernelWasAlreadyResolved(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $consoleRoutesFile = $modulePath . '/Routes/console.php';
        mkdir(\dirname($consoleRoutesFile), 0755, true);
        file_put_contents($consoleRoutesFile, '<?php // console routes');
        $app = new Application($this->tempDir);
        $kernel = new RecordingConsoleKernel();
        $app->instance(ConsoleKernelContract::class, $kernel);
        $app->make(ConsoleKernelContract::class);

        $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertSame([], $kernel->addedRoutePaths);

        $app->boot();

        self::assertContains($consoleRoutesFile, $kernel->addedRoutePaths);
    }

    #[Test]
    public function registersConsoleRouteFileImmediatelyWhenAppIsAlreadyBooted(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $consoleRoutesFile = $modulePath . '/Routes/console.php';
        mkdir(\dirname($consoleRoutesFile), 0755, true);
        file_put_contents($consoleRoutesFile, '<?php // console routes');
        $app = new Application($this->tempDir);
        $kernel = new RecordingConsoleKernel();
        $app->instance(ConsoleKernelContract::class, $kernel);
        $app->make(ConsoleKernelContract::class);
        $app->boot();

        $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertContains($consoleRoutesFile, $kernel->addedRoutePaths);
    }

    #[Test]
    public function returnsEarlyWhenConsoleRoutesFileIsMissing(): void
    {
        $app = new Application($this->tempDir);
        $kernel = new RecordingConsoleKernel();

        $report = $this->loader($app)
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog'));

        $app->singleton(ConsoleKernelContract::class, static fn(): RecordingConsoleKernel => $kernel);
        $app->make(ConsoleKernelContract::class);
        $app->boot();

        self::assertSame([], $kernel->addedRoutePaths);
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::FileNotFound, $report->reason);
    }

    #[Test]
    public function returnsEarlyWhenNotRunningInConsole(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $consoleRoutesFile = $modulePath . '/Routes/console.php';
        mkdir(\dirname($consoleRoutesFile), 0755, true);
        file_put_contents($consoleRoutesFile, '<?php // console routes');
        $app = new Application($this->tempDir);
        $reflection = new ReflectionProperty(Application::class, 'isRunningInConsole');
        $reflection->setValue($app, false);
        $kernel = new RecordingConsoleKernel();

        $report = $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath));

        $app->singleton(ConsoleKernelContract::class, static fn(): RecordingConsoleKernel => $kernel);
        $app->make(ConsoleKernelContract::class);
        $app->boot();

        self::assertSame([], $kernel->addedRoutePaths);
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::NotRunningInConsole, $report->reason);
    }

    private function loader(Application $app): ConsoleRouteLoader
    {
        return new ConsoleRouteLoader($app, new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout());
    }
}
