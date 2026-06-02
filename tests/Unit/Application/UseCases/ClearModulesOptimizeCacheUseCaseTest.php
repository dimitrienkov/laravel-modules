<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\UseCases\ClearModulesOptimizeCacheUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClearModulesOptimizeCacheUseCase::class)]
#[Group('lifecycle')]
final class ClearModulesOptimizeCacheUseCaseTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function clearsCacheAndResetsRegistry(): void
    {
        $cache = Mockery::mock(ModuleRegistryCacheInterface::class);
        $cache->expects('exists')->once()->andReturnTrue();
        $cache->expects('forget')->once();

        $registry = Mockery::mock(ModuleRegistryInterface::class);
        $registry->expects('reset')->once();

        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        $useCase = new ClearModulesOptimizeCacheUseCase($cache, $invalidator);
        $result = $useCase->execute();

        self::assertTrue($result->cleared);
    }

    #[Test]
    public function reportsNoOpWhenCacheDoesNotExist(): void
    {
        $cache = Mockery::mock(ModuleRegistryCacheInterface::class);
        $cache->expects('exists')->once()->andReturnFalse();
        $cache->shouldNotReceive('forget');

        $registry = Mockery::mock(ModuleRegistryInterface::class);
        $registry->shouldNotReceive('reset');

        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        $useCase = new ClearModulesOptimizeCacheUseCase($cache, $invalidator);
        $result = $useCase->execute();

        self::assertFalse($result->cleared);
    }

    #[Test]
    public function emitsStartedThenSucceededOnceOnTheHappyPath(): void
    {
        $cache = Mockery::mock(ModuleRegistryCacheInterface::class);
        $cache->expects('exists')->once()->andReturnTrue();
        $cache->expects('forget')->once();

        $registry = Mockery::mock(ModuleRegistryInterface::class);
        $registry->expects('reset')->once();

        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        $useCase = new ClearModulesOptimizeCacheUseCase($cache, $invalidator, $diagnostics);
        $useCase->execute();

        $diagnostics->shouldHaveReceived('lifecycleStarted')->once()->with(LifecycleOperation::ClearCache);
        $diagnostics->shouldHaveReceived('lifecycleSucceeded')->once()->with(LifecycleOperation::ClearCache);
        $diagnostics->shouldNotHaveReceived('lifecycleFailed');
    }
}
