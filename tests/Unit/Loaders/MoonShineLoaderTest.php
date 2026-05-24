<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\MoonShineLoader;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoonShineLoaderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_delegates_to_native_moonshine_autoload_for_module_namespace(): void
    {
        /** @var CoreContract&MockInterface $core */
        $core = Mockery::mock(CoreContract::class);
        /** @var Expectation $expectation */
        $expectation = $core->shouldReceive('autoload');
        $expectation->once()
            ->with('App\\Modules\\Blog')
            ->andReturn($core);

        (new MoonShineLoader($core))->load(ModuleFactory::make(namespace: 'App\\Modules\\Blog'));
    }

    #[Test]
    public function it_loads_after_other_default_loaders(): void
    {
        /** @var CoreContract&MockInterface $core */
        $core = Mockery::mock(CoreContract::class);

        self::assertSame(90, (new MoonShineLoader($core))->priority());
    }
}
