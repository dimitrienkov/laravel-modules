<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders\Pipeline;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleLoaderException;
use DimitrienkoV\LaravelModules\Loaders\Pipeline\ModuleLoaderPipeline;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Loaders\VO\PipelineRunSummary;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleLoaderPipeline::class)]
#[Group('loaders')]
final class ModuleLoaderPipelineTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function runsLoadersInPriorityOrder(): void
    {
        /** @var \ArrayObject<int, array{0: string, 1: string}> $calls */
        $calls = new \ArrayObject();

        $pipeline = new ModuleLoaderPipeline(
            registry: new PipelineFakeRegistry([
                ModuleFactory::make(name: 'blog'),
            ]),
            loaders: [
                new PipelineRecordingLoader($calls, 20, 'late'),
                new PipelineRecordingLoader($calls, 10, 'early'),
            ],
            exceptionHandler: $this->fakeExceptionHandler(),
            diagnostics: new NullModuleDiagnostics(),
        );

        $pipeline->boot();

        self::assertSame([
            ['early', 'blog'],
            ['late', 'blog'],
        ], $calls->getArrayCopy());
    }

    #[Test]
    public function preservesRegistrationOrderForEqualPriorities(): void
    {
        /** @var \ArrayObject<int, array{0: string, 1: string}> $calls */
        $calls = new \ArrayObject();

        $pipeline = new ModuleLoaderPipeline(
            registry: new PipelineFakeRegistry([
                ModuleFactory::make(name: 'blog'),
            ]),
            loaders: [
                new PipelineRecordingLoader($calls, 50, 'first'),
                new PipelineRecordingLoader($calls, 50, 'second'),
                new PipelineRecordingLoader($calls, 50, 'third'),
            ],
            exceptionHandler: $this->fakeExceptionHandler(),
            diagnostics: new NullModuleDiagnostics(),
        );

        $pipeline->boot();

        self::assertSame([
            ['first', 'blog'],
            ['second', 'blog'],
            ['third', 'blog'],
        ], $calls->getArrayCopy());
    }

    #[Test]
    public function skipsDisabledModules(): void
    {
        /** @var \ArrayObject<int, array{0: string, 1: string}> $calls */
        $calls = new \ArrayObject();

        $pipeline = new ModuleLoaderPipeline(
            registry: new PipelineFakeRegistry([
                ModuleFactory::make(name: 'enabled'),
                ModuleFactory::make(name: 'disabled', enabled: false),
            ]),
            loaders: [
                new PipelineRecordingLoader($calls, 10, 'loader'),
            ],
            exceptionHandler: $this->fakeExceptionHandler(),
            diagnostics: new NullModuleDiagnostics(),
        );

        $pipeline->boot();

        self::assertSame([['loader', 'enabled']], $calls->getArrayCopy());
    }

    #[Test]
    public function handlesEmptyLoaders(): void
    {
        $pipeline = new ModuleLoaderPipeline(
            registry: new PipelineFakeRegistry([
                ModuleFactory::make(name: 'blog'),
            ]),
            loaders: [],
            exceptionHandler: $this->fakeExceptionHandler(),
            diagnostics: new NullModuleDiagnostics(),
        );

        $pipeline->boot();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function continuesAfterLoaderExceptionAndReportsIt(): void
    {
        /** @var \ArrayObject<int, array{0: string, 1: string}> $calls */
        $calls = new \ArrayObject();
        $exception = new \RuntimeException('Loader failed');

        /** @var ExceptionHandler&Mockery\MockInterface $handler */
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')
            ->once()
            ->with(Mockery::on(
                static fn (object $reported): bool => $reported instanceof ModuleLoaderException
                    && $reported->loaderClass === PipelineConditionallyThrowingLoader::class
                    && $reported->moduleName === 'blog'
                    && $reported->modulePath !== ''
                    && $reported->getPrevious() === $exception,
            ));

        $pipeline = new ModuleLoaderPipeline(
            registry: new PipelineFakeRegistry([
                ModuleFactory::make(name: 'blog'),
                ModuleFactory::make(name: 'users'),
            ]),
            loaders: [
                new PipelineConditionallyThrowingLoader($calls, $exception, 10, 'blog'),
                new PipelineRecordingLoader($calls, 20, 'after'),
            ],
            exceptionHandler: $handler,
            diagnostics: new NullModuleDiagnostics(),
        );

        $pipeline->boot();

        self::assertSame([
            ['throwing', 'users'],
            ['after', 'blog'],
            ['after', 'users'],
        ], $calls->getArrayCopy());
    }

    #[Test]
    public function emitsPipelineAndLoaderOutcomeDiagnostics(): void
    {
        /** @var \ArrayObject<int, array{0: string, 1: string}> $calls */
        $calls = new \ArrayObject();

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $pipeline = new ModuleLoaderPipeline(
            registry: new PipelineFakeRegistry([
                ModuleFactory::make(name: 'blog'),
                ModuleFactory::make(name: 'disabled', enabled: false),
            ]),
            loaders: [
                new PipelineRecordingLoader($calls, 10, 'loader'),
            ],
            exceptionHandler: $this->fakeExceptionHandler(),
            diagnostics: $diagnostics,
        );

        $pipeline->boot();

        $diagnostics->shouldHaveReceived('pipelineStarted')->once()->with(1, 1);
        $diagnostics->shouldHaveReceived('loaderOutcome')->once();
        $diagnostics->shouldHaveReceived('pipelineFinished')
            ->once()
            ->with(Mockery::on(
                static fn (PipelineRunSummary $summary): bool => $summary->modulesEnabled === 1
                    && $summary->loaders === 1
                    && $summary->applied === 1
                    && $summary->skipped === 0
                    && $summary->failed === 0,
            ));
        $diagnostics->shouldNotHaveReceived('loaderFailed');
    }

    #[Test]
    public function reportsLoaderFailureToDiagnosticsInAdditionToHandler(): void
    {
        /** @var \ArrayObject<int, array{0: string, 1: string}> $calls */
        $calls = new \ArrayObject();
        $exception = new \RuntimeException('boom');

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $pipeline = new ModuleLoaderPipeline(
            registry: new PipelineFakeRegistry([
                ModuleFactory::make(name: 'blog'),
            ]),
            loaders: [
                new PipelineConditionallyThrowingLoader($calls, $exception, 10, 'blog'),
            ],
            exceptionHandler: $this->fakeExceptionHandler(),
            diagnostics: $diagnostics,
        );

        $pipeline->boot();

        $diagnostics->shouldHaveReceived('loaderFailed')->once();
        $diagnostics->shouldHaveReceived('pipelineFinished')
            ->once()
            ->with(Mockery::on(
                static fn (PipelineRunSummary $summary): bool => $summary->failed === 1 && $summary->applied === 0,
            ));
    }

    #[Test]
    public function countsAppliedSkippedAndFailedAndMeasuresDuration(): void
    {
        /** @var \ArrayObject<int, array{0: string, 1: string}> $calls */
        $calls = new \ArrayObject();

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $pipeline = new ModuleLoaderPipeline(
            registry: new PipelineFakeRegistry([
                ModuleFactory::make(name: 'blog'),
            ]),
            loaders: [
                new PipelineStaticReportLoader(LoadReport::applied(['x' => ['a']]), 10),
                new PipelineStaticReportLoader(LoadReport::skipped(SkipReason::NoDirectory), 20),
                new PipelineConditionallyThrowingLoader($calls, new \RuntimeException('boom'), 30, 'blog'),
            ],
            exceptionHandler: $this->fakeExceptionHandler(),
            diagnostics: $diagnostics,
        );

        $pipeline->boot();

        $diagnostics->shouldHaveReceived('loaderFailed')->once();
        $diagnostics->shouldHaveReceived('pipelineFinished')
            ->once()
            ->with(Mockery::on(
                static fn (PipelineRunSummary $summary): bool => $summary->applied === 1
                    && $summary->skipped === 1
                    && $summary->failed === 1
                    && $summary->durationMs >= 0.0,
            ));
    }

    private function fakeExceptionHandler(): ExceptionHandler
    {
        /** @var ExceptionHandler&Mockery\MockInterface $handler */
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report');

        return $handler;
    }
}

final readonly class PipelineStaticReportLoader implements LoaderInterface
{
    public function __construct(
        private LoadReport $report,
        private int $priority,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        return $this->report;
    }

    public function priority(): int
    {
        return $this->priority;
    }
}

final class PipelineRecordingLoader implements LoaderInterface
{
    /**
     * @param \ArrayObject<int, array{0: string, 1: string}> $calls
     */
    public function __construct(
        private readonly \ArrayObject $calls,
        private readonly int $priority,
        private readonly string $name,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        $this->calls->append([$this->name, $module->name]);

        return LoadReport::applied();
    }

    public function priority(): int
    {
        return $this->priority;
    }
}

final readonly class PipelineConditionallyThrowingLoader implements LoaderInterface
{
    /**
     * @param \ArrayObject<int, array{0: string, 1: string}> $calls
     */
    public function __construct(
        private \ArrayObject $calls,
        private \Throwable $exception,
        private int $priority,
        private string $moduleName,
    ) {
    }

    public function load(Module $module): LoadReport
    {
        if ($module->name === $this->moduleName) {
            throw $this->exception;
        }

        $this->calls->append(['throwing', $module->name]);

        return LoadReport::applied();
    }

    public function priority(): int
    {
        return $this->priority;
    }
}

final readonly class PipelineFakeRegistry implements ModuleRegistryInterface
{
    /**
     * @param array<int, Module> $modules
     */
    public function __construct(
        private array $modules,
    ) {
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function find(string $name): Module
    {
        throw new \RuntimeException("Module [{$name}] was not registered.");
    }

    public function has(string $name): bool
    {
        foreach ($this->modules as $module) {
            if ($module->name === $name) {
                return true;
            }
        }

        return false;
    }

    public function reset(): void
    {
    }
}
