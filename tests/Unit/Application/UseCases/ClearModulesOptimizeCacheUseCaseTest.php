<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\UseCases\ClearModulesOptimizeCacheUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClearModulesOptimizeCacheUseCaseTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function it_clears_cache_and_resets_registry(): void
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
    public function it_reports_no_op_when_cache_does_not_exist(): void
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
}
