<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\CommandLoader;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\Stubs\CommandRecordingKernel;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandLoader::class)]
#[Group('loaders')]
final class CommandLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('command-loader');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function registersCommandPathsViaKernel(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $commandsDir = $modulePath . '/Console/Commands';
        mkdir($commandsDir, 0755, true);
        file_put_contents($commandsDir . '/PublishPostCommand.php', '<?php // command');
        $app = new Application($this->tempDir);
        $kernel = new CommandRecordingKernel();

        $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $app->singleton(ConsoleKernelContract::class, static fn (): CommandRecordingKernel => $kernel);
        $app->make(ConsoleKernelContract::class);

        self::assertSame([], $kernel->addedCommandPaths);

        $app->boot();

        self::assertContains($commandsDir, $kernel->addedCommandPaths);
    }

    #[Test]
    public function registersCommandPathsWhenContractKernelWasAlreadyResolved(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $commandsDir = $modulePath . '/Console/Commands';
        mkdir($commandsDir, 0755, true);
        $app = new Application($this->tempDir);
        $kernel = new CommandRecordingKernel();
        $app->instance(ConsoleKernelContract::class, $kernel);
        $app->make(ConsoleKernelContract::class);

        $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame([], $kernel->addedCommandPaths);

        $app->boot();

        self::assertContains($commandsDir, $kernel->addedCommandPaths);
    }

    #[Test]
    public function registersCommandPathsImmediatelyWhenAppIsAlreadyBooted(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $commandsDir = $modulePath . '/Console/Commands';
        mkdir($commandsDir, 0755, true);
        $app = new Application($this->tempDir);
        $kernel = new CommandRecordingKernel();
        $app->instance(ConsoleKernelContract::class, $kernel);
        $app->make(ConsoleKernelContract::class);
        $app->boot();

        $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertContains($commandsDir, $kernel->addedCommandPaths);
    }

    #[Test]
    public function returnsEarlyWhenCommandsDirectoryIsMissing(): void
    {
        $app = new Application($this->tempDir);
        $kernel = new CommandRecordingKernel();

        $this->loader($app)
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog'));

        $app->singleton(ConsoleKernelContract::class, static fn (): CommandRecordingKernel => $kernel);
        $app->make(ConsoleKernelContract::class);
        $app->boot();

        self::assertSame([], $kernel->addedCommandPaths);
    }

    #[Test]
    public function returnsEarlyWhenNotRunningInConsole(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $commandsDir = $modulePath . '/Console/Commands';
        mkdir($commandsDir, 0755, true);
        file_put_contents($commandsDir . '/SomeCommand.php', '<?php // command');
        $app = new Application($this->tempDir);
        $reflection = new \ReflectionProperty(Application::class, 'isRunningInConsole');
        $reflection->setValue($app, false);
        $kernel = new CommandRecordingKernel();

        $this->loader($app)
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $app->singleton(ConsoleKernelContract::class, static fn (): CommandRecordingKernel => $kernel);
        $app->make(ConsoleKernelContract::class);
        $app->boot();

        self::assertSame([], $kernel->addedCommandPaths);
    }

    private function loader(Application $app): CommandLoader
    {
        return new CommandLoader($app, new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout());
    }
}
