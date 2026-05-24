<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Loaders\MoonShineLoader;
use DimitrienkoV\LaravelModules\Manifest\Module;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use Illuminate\Foundation\Application;
use Mockery;
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

        $loaderClasses = $this->loaderClasses();

        self::assertNotContains(MoonShineLoader::class, $loaderClasses);
    }

    #[Test]
    public function provider_registers_moonshine_loader_when_core_is_bound(): void
    {
        /** @var CoreContract&MockInterface $core */
        $core = Mockery::mock(CoreContract::class);
        $this->application()->instance(CoreContract::class, $core);
        $provider = $this->provider();

        $provider->register();

        self::assertContains(MoonShineLoader::class, $this->loaderClasses());
    }

    /**
     * @return array<int, class-string>
     */
    private function loaderClasses(): array
    {
        return array_map(
            static fn (object $loader): string => $loader::class,
            iterator_to_array($this->application()->tagged(ModuleLoaderServiceProvider::LOADER_TAG)),
        );
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
    public function all(): array
    {
        return [];
    }

    public function find(string $name): Module
    {
        throw new \RuntimeException("Module [{$name}] was not registered in fake registry.");
    }

    public function loadOrder(): array
    {
        return [];
    }
}
