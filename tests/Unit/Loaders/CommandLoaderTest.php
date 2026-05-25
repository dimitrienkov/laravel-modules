<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\CommandLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\Stubs\CommandRecordingKernel;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function it_registers_command_paths_via_kernel(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $commandsDir = $modulePath . '/Console/Commands';
        mkdir($commandsDir, 0755, true);
        file_put_contents($commandsDir . '/PublishPostCommand.php', '<?php // command');
        $app = new Application($this->tempDir);
        $kernel = new CommandRecordingKernel();

        (new CommandLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $app->singleton(ConsoleKernel::class, static fn (): CommandRecordingKernel => $kernel);
        $app->make(ConsoleKernel::class);

        self::assertContains($commandsDir, $kernel->addedCommandPaths);
    }

    #[Test]
    public function it_returns_early_when_commands_directory_is_missing(): void
    {
        $app = new Application($this->tempDir);
        $kernel = new CommandRecordingKernel();

        (new CommandLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog'));

        $app->singleton(ConsoleKernel::class, static fn (): CommandRecordingKernel => $kernel);
        $app->make(ConsoleKernel::class);

        self::assertSame([], $kernel->addedCommandPaths);
    }

    #[Test]
    public function it_returns_early_when_not_running_in_console(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $commandsDir = $modulePath . '/Console/Commands';
        mkdir($commandsDir, 0755, true);
        file_put_contents($commandsDir . '/SomeCommand.php', '<?php // command');
        $app = new Application($this->tempDir);
        $reflection = new \ReflectionProperty(Application::class, 'isRunningInConsole');
        $reflection->setValue($app, false);
        $kernel = new CommandRecordingKernel();

        (new CommandLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $app->singleton(ConsoleKernel::class, static fn (): CommandRecordingKernel => $kernel);
        $app->make(ConsoleKernel::class);

        self::assertSame([], $kernel->addedCommandPaths);
    }
}
