<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders\Pipeline;

use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleLoaderException;
use DimitrienkoV\LaravelModules\Loaders\Pipeline\ModuleLoaderPipeline;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleLoaderPipelineTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function it_runs_loaders_in_priority_order(): void
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
        );

        $pipeline->boot();

        self::assertSame([
            ['early', 'blog'],
            ['late', 'blog'],
        ], $calls->getArrayCopy());
    }

    #[Test]
    public function it_preserves_registration_order_for_equal_priorities(): void
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
        );

        $pipeline->boot();

        self::assertSame([
            ['first', 'blog'],
            ['second', 'blog'],
            ['third', 'blog'],
        ], $calls->getArrayCopy());
    }

    #[Test]
    public function it_skips_disabled_modules(): void
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
        );

        $pipeline->boot();

        self::assertSame([['loader', 'enabled']], $calls->getArrayCopy());
    }

    #[Test]
    public function it_handles_empty_loaders(): void
    {
        $pipeline = new ModuleLoaderPipeline(
            registry: new PipelineFakeRegistry([
                ModuleFactory::make(name: 'blog'),
            ]),
            loaders: [],
            exceptionHandler: $this->fakeExceptionHandler(),
        );

        $pipeline->boot();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_continues_after_loader_exception_and_reports_it(): void
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
        );

        $pipeline->boot();

        self::assertSame([
            ['throwing', 'users'],
            ['after', 'blog'],
            ['after', 'users'],
        ], $calls->getArrayCopy());
    }

    private function fakeExceptionHandler(): ExceptionHandler
    {
        /** @var ExceptionHandler&Mockery\MockInterface $handler */
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report');

        return $handler;
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

    public function load(Module $module): void
    {
        $this->calls->append([$this->name, $module->name]);
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

    public function load(Module $module): void
    {
        if ($module->name === $this->moduleName) {
            throw $this->exception;
        }

        $this->calls->append(['throwing', $module->name]);
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
