<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\ConsoleRouteLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\Stubs\RecordingConsoleKernel;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function it_registers_console_route_file_via_kernel_after_boot(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $consoleRoutesFile = $modulePath . '/Routes/console.php';
        mkdir(\dirname($consoleRoutesFile), 0755, true);
        file_put_contents($consoleRoutesFile, '<?php // console routes');
        $app = new Application($this->tempDir);
        $kernel = new RecordingConsoleKernel();

        (new ConsoleRouteLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        $app->singleton(ConsoleKernel::class, static fn (): RecordingConsoleKernel => $kernel);
        $app->make(ConsoleKernel::class);

        self::assertSame([], $kernel->addedRoutePaths);

        $app->boot();

        self::assertContains($consoleRoutesFile, $kernel->addedRoutePaths);
    }

    #[Test]
    public function it_returns_early_when_console_routes_file_is_missing(): void
    {
        $app = new Application($this->tempDir);
        $kernel = new RecordingConsoleKernel();

        (new ConsoleRouteLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog'));

        $app->singleton(ConsoleKernel::class, static fn (): RecordingConsoleKernel => $kernel);
        $app->make(ConsoleKernel::class);

        self::assertSame([], $kernel->addedRoutePaths);
    }

    #[Test]
    public function it_returns_early_when_not_running_in_console(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $consoleRoutesFile = $modulePath . '/Routes/console.php';
        mkdir(\dirname($consoleRoutesFile), 0755, true);
        file_put_contents($consoleRoutesFile, '<?php // console routes');
        $app = new Application($this->tempDir);
        $reflection = new \ReflectionProperty(Application::class, 'isRunningInConsole');
        $reflection->setValue($app, false);
        $kernel = new RecordingConsoleKernel();

        (new ConsoleRouteLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        $app->singleton(ConsoleKernel::class, static fn (): RecordingConsoleKernel => $kernel);
        $app->make(ConsoleKernel::class);
        $app->boot();

        self::assertSame([], $kernel->addedRoutePaths);
    }
}
