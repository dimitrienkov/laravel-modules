<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\MoonShine;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleDetailPage;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleFormPage;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleIndexPage;
use DimitrienkoV\LaravelModules\MoonShine\Resources\ModulesResource;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\FakeModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Foundation\Application;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers config-/stack-gated registration of the admin UI on the MoonShine core.
 * The per-module autoload bridge runs whenever core is present; the admin resource
 * and its pages are registered only when `modules.moonshine.enabled` is true.
 */
#[Group('feature')]
final class ModuleAdminRegistrationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function registersResourceAndPagesWhenEnabled(): void
    {
        $core = $this->core();
        $core->shouldReceive('autoload')->andReturnSelf();
        $core->shouldReceive('resources')->once()->with([ModulesResource::class])->andReturnSelf();
        $core->shouldReceive('pages')->once()
            ->with([ModuleIndexPage::class, ModuleFormPage::class, ModuleDetailPage::class])
            ->andReturnSelf();

        $this->bootWithCore($core, enabled: true);
    }

    #[Test]
    public function skipsResourceRegistrationButKeepsAutoloadWhenDisabled(): void
    {
        $core = $this->core();
        // The per-module autoload bridge must still run for the enabled module...
        $core->shouldReceive('autoload')->once()->with('App\\Modules\\Blog')->andReturnSelf();
        // ...but the admin resource/pages must not be registered.
        $core->shouldNotReceive('resources');
        $core->shouldNotReceive('pages');

        $this->bootWithCore($core, enabled: false);
    }

    private function bootWithCore(CoreContract&MockInterface $core, bool $enabled): void
    {
        $app = $this->application();
        $app['config']->set('modules.moonshine.enabled', $enabled);

        $provider = new ModuleLoaderServiceProvider($app);
        $provider->register();

        $app->instance(ModuleRegistryInterface::class, $this->registry());
        $app->singleton(CoreContract::class, static fn(): CoreContract => $core);

        $provider->boot();

        // Resolving the core triggers the deferred registration callbacks.
        $app->make(CoreContract::class);
    }

    private function registry(): FakeModuleRegistry
    {
        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'blog', namespace: 'App\\Modules\\Blog', enabled: true));

        return $registry;
    }

    private function core(): CoreContract&MockInterface
    {
        /** @var CoreContract&MockInterface */
        return Mockery::mock(CoreContract::class);
    }

    private function application(): Application
    {
        if ($this->app === null) {
            self::fail('Testbench application is not initialized.');
        }

        return $this->app;
    }
}
