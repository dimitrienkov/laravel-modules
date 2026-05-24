<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\ConsoleRouteLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConsoleRouteLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-console-route-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_registers_console_route_file_via_kernel(): void
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

        self::assertSame([], $kernel->addedRoutePaths);
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

final class RecordingConsoleKernel extends ConsoleKernel
{
    /** @var list<string> */
    public array $addedRoutePaths = [];

    public function __construct()
    {
    }

    /**
     * @param list<string> $paths
     */
    public function addCommandRoutePaths(array $paths): static
    {
        $this->addedRoutePaths = [...$this->addedRoutePaths, ...$paths];

        return $this;
    }
}
