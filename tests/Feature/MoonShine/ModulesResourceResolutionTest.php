<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\MoonShine;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\FakeModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Mockery;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
final class ModulesResourceResolutionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ModuleLoaderServiceProvider::class];
    }

    #[Test]
    public function resolvesFromContainerAndMapsTheRegistry(): void
    {
        // A bare CoreContract is enough: the resource autowires our own contracts
        // and never calls the core during construction or getItems().
        $this->app->instance(CoreContract::class, Mockery::mock(CoreContract::class));

        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'blog', enabled: true));
        $this->app->instance(ModuleRegistryInterface::class, $registry);

        $resource = $this->app->make(ModulesResource::class);

        self::assertInstanceOf(ModulesResource::class, $resource);

        $items = $resource->getItems();
        self::assertCount(1, $items);
        self::assertSame('blog', $items[0]->name);
        self::assertSame('blog', $resource->getCaster()->cast($items[0])->getKey());
    }
}
