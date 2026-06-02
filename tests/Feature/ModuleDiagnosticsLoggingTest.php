<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Loaders\Pipeline\ModuleLoaderPipeline;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Support\Logging\ModuleLogger;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
final class ModuleDiagnosticsLoggingTest extends TestCase
{
    #[Test]
    public function bindsModuleLoggerAndWritesEventsToTheConfiguredChannelWhenEnabled(): void
    {
        $this->app['config']->set('modules.logging.enabled', true);
        $this->resetDiagnostics();

        $diagnostics = $this->app->make(ModuleDiagnosticsInterface::class);
        self::assertInstanceOf(ModuleLogger::class, $diagnostics);

        $diagnostics->discoveryCompleted(2, 1, 1);
        $diagnostics->cacheWritten(2, 'bootstrap/cache/modules.php');
        $diagnostics->lifecycleStarted(LifecycleOperation::Install, 'blog', 'zip');

        $messages = $this->recordedMessages();
        self::assertContains('discovery.completed', $messages);
        self::assertContains('cache.written', $messages);
        self::assertContains('lifecycle.install.started', $messages);
    }

    #[Test]
    public function bindsTheNullObjectAndStaysCompletelySilentWhenDisabled(): void
    {
        $this->app['config']->set('modules.logging.enabled', false);
        $this->resetDiagnostics();

        $diagnostics = $this->app->make(ModuleDiagnosticsInterface::class);
        self::assertInstanceOf(NullModuleDiagnostics::class, $diagnostics);

        $diagnostics->discoveryCompleted(2, 1, 1);
        $diagnostics->cacheWritten(2, 'bootstrap/cache/modules.php');
        $diagnostics->lifecycleStarted(LifecycleOperation::Install, 'blog', 'zip');

        self::assertSame([], $this->recordedMessages());
    }

    #[Test]
    public function writesToTheDefaultChannelWhenNoChannelIsConfigured(): void
    {
        $this->app['config']->set('modules.logging.enabled', true);
        $this->app['config']->set('modules.logging.channel', null);
        $this->app['config']->set('logging.default', 'modules_test');
        $this->resetDiagnostics();

        $diagnostics = $this->app->make(ModuleDiagnosticsInterface::class);
        $diagnostics->cacheCleared();

        self::assertContains('cache.cleared', $this->recordedMessages());
    }

    #[Test]
    public function aThrowingLogChannelNeverBreaksPipelineBoot(): void
    {
        $this->app['config']->set('modules.logging.enabled', true);
        $this->app['config']->set('modules.logging.channel', 'modules_broken');
        $this->resetDiagnostics();

        $diagnostics = $this->app->make(ModuleDiagnosticsInterface::class);
        self::assertInstanceOf(ModuleLogger::class, $diagnostics);

        $pipeline = new ModuleLoaderPipeline(
            registry: new class () implements ModuleRegistryInterface {
                public function all(): array
                {
                    return [];
                }

                public function find(string $name): Module
                {
                    throw new \RuntimeException('not used by this test');
                }

                public function has(string $name): bool
                {
                    return false;
                }

                public function reset(): void
                {
                }
            },
            loaders: [],
            exceptionHandler: $this->app->make(ExceptionHandler::class),
            diagnostics: $diagnostics,
        );

        // boot() drives pipelineStarted/pipelineFinished through the broken
        // channel; the flag flips only if none of it threw out of the logger.
        $booted = false;
        $pipeline->boot();
        $booted = true;

        self::assertTrue($booted, 'pipeline boot must complete despite a throwing log channel');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ModuleLoaderServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('logging.channels.modules_test', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
        ]);
        $app['config']->set('logging.channels.modules_broken', [
            'driver' => 'monolog',
            'handler' => ThrowingLogHandler::class,
        ]);
        $app['config']->set('modules.logging.channel', 'modules_test');
        $app['config']->set('modules.logging.level', 'debug');
        $app['config']->set('modules.logging.events', [
            'discovery' => true,
            'cache' => true,
            'pipeline' => true,
            'lifecycle' => true,
        ]);
    }

    private function resetDiagnostics(): void
    {
        $this->app->forgetInstance(ModuleDiagnosticsInterface::class);
        Log::forgetChannel('modules_test');
    }

    /**
     * @return list<string>
     */
    private function recordedMessages(): array
    {
        $monolog = Log::channel('modules_test')->getLogger();

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                return array_map(
                    static fn (LogRecord $record): string => $record->message,
                    $handler->getRecords(),
                );
            }
        }

        self::fail('The modules_test channel is not backed by a TestHandler.');
    }
}

/**
 * A Monolog handler that fails on every write, simulating a host log channel
 * whose backing store is broken (disk full, permission denied, remote down).
 */
final class ThrowingLogHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        throw new \RuntimeException('log channel is broken');
    }
}
