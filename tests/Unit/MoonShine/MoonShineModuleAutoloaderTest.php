<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\MoonShine;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\MoonShine\MoonShineModuleAutoloader;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoonShineModuleAutoloaderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_autoloads_enabled_module_namespaces_via_core_contract(): void
    {
        /** @var CoreContract&MockInterface $core */
        $core = Mockery::mock(CoreContract::class);
        /** @var Expectation $expectation */
        $expectation = $core->shouldReceive('autoload');
        $expectation->once()
            ->with('App\\Modules\\Blog')
            ->andReturn($core);

        /** @var ModuleRegistryInterface&MockInterface $registry */
        $registry = Mockery::mock(ModuleRegistryInterface::class);
        /** @var Expectation $loadOrder */
        $loadOrder = $registry->shouldReceive('loadOrder');
        $loadOrder->once()
            ->andReturn([ModuleFactory::make(name: 'blog', namespace: 'App\\Modules\\Blog')]);

        (new MoonShineModuleAutoloader($registry))->autoload($core);
    }

    #[Test]
    public function it_skips_disabled_modules(): void
    {
        /** @var CoreContract&MockInterface $core */
        $core = Mockery::mock(CoreContract::class);
        $core->shouldNotReceive('autoload');

        /** @var ModuleRegistryInterface&MockInterface $registry */
        $registry = Mockery::mock(ModuleRegistryInterface::class);
        /** @var Expectation $loadOrder */
        $loadOrder = $registry->shouldReceive('loadOrder');
        $loadOrder->once()
            ->andReturn([ModuleFactory::make(name: 'disabled', enabled: false)]);

        (new MoonShineModuleAutoloader($registry))->autoload($core);
    }
}
