<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\CommandLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandLoaderTest extends TestCase
{
    private string $tempDir;

    /** @var list<callable> */
    private array $autoloaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-command-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $this->autoloaders = [];
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_discovers_and_registers_valid_command_classes(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $commandsDir = $modulePath . '/Console/Commands';
        mkdir($commandsDir, 0755, true);
        file_put_contents(
            $commandsDir . '/PublishPostCommand.php',
            '<?php namespace App\\Modules\\Blog\\Console\\Commands; class PublishPostCommand extends \\Illuminate\\Console\\Command { protected $signature = "blog:publish"; public function handle(): void {} }',
        );
        $this->registerAutoloader($commandsDir . '/PublishPostCommand.php', 'App\\Modules\\Blog\\Console\\Commands\\PublishPostCommand');
        $app = new Application($this->tempDir);
        $kernel = new CommandRecordingKernel();

        (new CommandLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $app->singleton(ConsoleKernel::class, static fn (): CommandRecordingKernel => $kernel);
        $app->make(ConsoleKernel::class);

        self::assertContains('App\\Modules\\Blog\\Console\\Commands\\PublishPostCommand', $kernel->addedCommands);
    }

    #[Test]
    public function it_skips_non_command_and_abstract_classes(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $commandsDir = $modulePath . '/Console/Commands';
        mkdir($commandsDir, 0755, true);
        file_put_contents(
            $commandsDir . '/NotACommand.php',
            '<?php namespace App\\Modules\\Blog\\Console\\Commands; class NotACommand {}',
        );
        file_put_contents(
            $commandsDir . '/AbstractCommand.php',
            '<?php namespace App\\Modules\\Blog\\Console\\Commands; abstract class AbstractCommand extends \\Illuminate\\Console\\Command {}',
        );
        $this->registerAutoloader($commandsDir . '/NotACommand.php', 'App\\Modules\\Blog\\Console\\Commands\\NotACommand');
        $this->registerAutoloader($commandsDir . '/AbstractCommand.php', 'App\\Modules\\Blog\\Console\\Commands\\AbstractCommand');
        $app = new Application($this->tempDir);
        $kernel = new CommandRecordingKernel();

        (new CommandLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $app->singleton(ConsoleKernel::class, static fn (): CommandRecordingKernel => $kernel);
        $app->make(ConsoleKernel::class);

        self::assertSame([], $kernel->addedCommands);
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

        self::assertSame([], $kernel->addedCommands);
    }

    #[Test]
    public function it_returns_early_when_not_running_in_console(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $commandsDir = $modulePath . '/Console/Commands';
        mkdir($commandsDir, 0755, true);
        file_put_contents(
            $commandsDir . '/SomeCommand.php',
            '<?php namespace App\\Modules\\Blog\\Console\\Commands; class SomeCommand extends \\Illuminate\\Console\\Command { protected $signature = "blog:some"; public function handle(): void {} }',
        );
        $app = new Application($this->tempDir);
        $reflection = new \ReflectionProperty(Application::class, 'isRunningInConsole');
        $reflection->setValue($app, false);
        $kernel = new CommandRecordingKernel();

        (new CommandLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $app->singleton(ConsoleKernel::class, static fn (): CommandRecordingKernel => $kernel);
        $app->make(ConsoleKernel::class);

        self::assertSame([], $kernel->addedCommands);
    }

    private function registerAutoloader(string $file, string $class): void
    {
        $autoloader = static function (string $requested) use ($file, $class): void {
            if ($requested === $class) {
                require_once $file;
            }
        };

        spl_autoload_register($autoloader);
        $this->autoloaders[] = $autoloader;
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

final class CommandRecordingKernel extends ConsoleKernel
{
    /** @var list<string> */
    public array $addedCommands = [];

    public function __construct()
    {
    }

    /**
     * @param list<string> $commands
     */
    public function addCommands(array $commands): static
    {
        $this->addedCommands = [...$this->addedCommands, ...$commands];

        return $this;
    }
}
