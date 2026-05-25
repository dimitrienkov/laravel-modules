<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Foundation\Application;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class OptionalMoonShineBootTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function provider_boots_without_bound_moonshine_core(): void
    {
        $provider = $this->provider();
        $provider->register();
        $this->application()->instance(ModuleRegistryInterface::class, new MoonShineFakeRegistry());

        $provider->boot();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function moonshine_autoloads_modules_when_core_resolves_after_provider_boot(): void
    {
        /** @var CoreContract&MockInterface $core */
        $core = Mockery::mock(CoreContract::class);
        /** @var Expectation $expectation */
        $expectation = $core->shouldReceive('autoload');
        $expectation->once()
            ->with('App\\Modules\\Blog')
            ->andReturn($core);

        $app = $this->application();
        $provider = $this->provider();
        $provider->register();

        $app->instance(ModuleRegistryInterface::class, new MoonShineFakeRegistry([
            ModuleFactory::make(name: 'blog', namespace: 'App\\Modules\\Blog'),
            ModuleFactory::make(name: 'disabled', enabled: false),
        ]));

        $app->singleton(CoreContract::class, static fn () => $core);
        $provider->boot();

        $app->make(CoreContract::class);
    }

    #[Test]
    public function moonshine_autoloads_modules_when_core_was_resolved_before_provider_boot(): void
    {
        /** @var CoreContract&MockInterface $core */
        $core = Mockery::mock(CoreContract::class);
        /** @var Expectation $expectation */
        $expectation = $core->shouldReceive('autoload');
        $expectation->once()
            ->with('App\\Modules\\Blog')
            ->andReturn($core);

        $app = $this->application();
        $provider = $this->provider();
        $provider->register();

        $app->instance(ModuleRegistryInterface::class, new MoonShineFakeRegistry([
            ModuleFactory::make(name: 'blog', namespace: 'App\\Modules\\Blog'),
            ModuleFactory::make(name: 'disabled', enabled: false),
        ]));

        $app->singleton(CoreContract::class, static fn () => $core);
        $app->make(CoreContract::class);

        $provider->boot();
    }

    #[Test]
    public function moonshine_loader_is_not_in_tagged_pipeline(): void
    {
        $provider = $this->provider();
        $provider->register();
        $this->application()->instance(ModuleRegistryInterface::class, new MoonShineFakeRegistry());
        $provider->boot();

        $loaderClasses = array_map(
            static fn (object $loader): string => $loader::class,
            iterator_to_array($this->application()->tagged(ModuleLoaderServiceProvider::LOADER_TAG)),
        );

        self::assertNotContains('DimitrienkoV\\LaravelModules\\Loaders\\MoonShineLoader', $loaderClasses);
    }

    private function provider(): ModuleLoaderServiceProvider
    {
        return new ModuleLoaderServiceProvider($this->application());
    }

    private function application(): Application
    {
        if ($this->app === null) {
            self::fail('Testbench application is not initialized.');
        }

        return $this->app;
    }
}

final readonly class MoonShineFakeRegistry implements ModuleRegistryInterface
{
    /**
     * @param array<int, Module> $modules
     */
    public function __construct(
        private array $modules = [],
    ) {
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function find(string $name): Module
    {
        throw new \RuntimeException("Module [{$name}] was not registered in fake registry.");
    }

    public function loadOrder(): array
    {
        return $this->modules;
    }
}
