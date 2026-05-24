<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Manifest\FeatureRepository;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Support\ComposerNamespaceResolver;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModuleLoaderServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_core_bindings(): void
    {
        $this->provider()->register();
        $app = $this->application();

        self::assertInstanceOf(ManifestValidator::class, $app->make(ManifestValidatorInterface::class));
        self::assertInstanceOf(ComposerNamespaceResolver::class, $app->make(NamespaceResolverInterface::class));
        self::assertInstanceOf(
            ModuleManifestRepository::class,
            $app->make(ModuleManifestRepositoryInterface::class),
        );
        self::assertInstanceOf(ModuleRegistry::class, $app->make(ModuleRegistryInterface::class));
        self::assertInstanceOf(FeatureRepository::class, $app->make(FeatureRepositoryInterface::class));
    }

    #[Test]
    public function feature_repository_binding_is_scoped(): void
    {
        $this->provider()->register();
        $app = $this->application();

        $first = $app->make(FeatureRepositoryInterface::class);
        $sameScope = $app->make(FeatureRepositoryInterface::class);
        $app->forgetScopedInstances();
        $nextScope = $app->make(FeatureRepositoryInterface::class);

        self::assertSame($first, $sameScope);
        self::assertNotSame($first, $nextScope);
    }

    #[Test]
    public function it_runs_tagged_loaders_for_enabled_modules_once_in_priority_order(): void
    {
        $provider = $this->provider();
        $provider->register();
        $app = $this->application();
        /** @var \ArrayObject<int, array{0: string, 1: string}> $calls */
        $calls = new \ArrayObject();

        $app->instance(ModuleRegistryInterface::class, new FakeRegistry([
            ModuleFactory::make(name: 'enabled'),
            ModuleFactory::make(name: 'disabled', enabled: false),
            ModuleFactory::make(name: 'second'),
        ]));
        $app->singleton('loader.late', function () use (&$calls): RecordingLoader {
            return new RecordingLoader($calls, 20, 'late');
        });
        $app->singleton('loader.early', function () use (&$calls): RecordingLoader {
            return new RecordingLoader($calls, 10, 'early');
        });
        $app->tag(['loader.late', 'loader.early'], ModuleLoaderServiceProvider::LOADER_TAG);

        $provider->boot();

        self::assertSame([
            ['early', 'enabled'],
            ['early', 'second'],
            ['late', 'enabled'],
            ['late', 'second'],
        ], $calls->getArrayCopy());
    }

    #[Test]
    public function it_boots_without_moonshine_loader_class(): void
    {
        $provider = $this->provider();
        $provider->register();
        $this->application()->instance(ModuleRegistryInterface::class, new FakeRegistry([]));

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

final class RecordingLoader implements LoaderInterface
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

final readonly class FakeRegistry implements ModuleRegistryInterface
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
        foreach ($this->modules as $module) {
            if ($module->name === $name) {
                return $module;
            }
        }

        throw new \RuntimeException("Module [{$name}] was not registered in fake registry.");
    }

    public function loadOrder(): array
    {
        return $this->modules;
    }
}
