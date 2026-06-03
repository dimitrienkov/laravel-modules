<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadReport;
use DimitrienkoV\LaravelModules\Manifest\FeatureRepository;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Support\ApplicationNamespaceResolver;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ArrayObject;
use ReflectionProperty;
use RuntimeException;
use stdClass;

#[Group('feature')]
final class ModuleLoaderServiceProviderTest extends TestCase
{
    #[Test]
    public function registersCoreBindings(): void
    {
        $this->provider()->register();
        $app = $this->application();

        self::assertInstanceOf(ManifestValidator::class, $app->make(ManifestValidatorInterface::class));
        self::assertInstanceOf(ContainerLifecycleHooks::class, $app->make(ContainerLifecycleHooks::class));
        self::assertInstanceOf(ApplicationNamespaceResolver::class, $app->make(NamespaceResolverInterface::class));
        self::assertInstanceOf(
            ModuleManifestRepository::class,
            $app->make(ModuleManifestRepositoryInterface::class),
        );
        self::assertInstanceOf(ModuleRegistry::class, $app->make(ModuleRegistryInterface::class));
        self::assertInstanceOf(FeatureRepository::class, $app->make(FeatureRepositoryInterface::class));
    }

    #[Test]
    public function featureRepositoryBindingIsScoped(): void
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
    public function featureRepositoryIsRecordedAsAScopedContainerBinding(): void
    {
        $this->provider()->register();
        $app = $this->application();

        // The container records every scoped abstract in `$scopedInstances`;
        // Octane flushes exactly those on each `OperationTerminated`. Asserting
        // membership here locks the registration itself — a regression to
        // `singleton()` drops the abstract from this list and fails the test,
        // even though a behavioural same/not-same check could be fooled by an
        // accidental fresh instance.
        $property = new ReflectionProperty(Container::class, 'scopedInstances');
        /** @var list<string> $scopedInstances */
        $scopedInstances = $property->getValue($app);

        self::assertContains(
            FeatureRepositoryInterface::class,
            $scopedInstances,
            'FeatureRepository must be bound as scoped so Octane resets per-request feature state.',
        );
    }

    #[Test]
    public function rejectsNonArrayModuleDirectoriesConfig(): void
    {
        $this->provider()->register();
        $app = $this->application();
        $app['config']->set('modules.paths.directories', 'not-an-array');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a list of directory paths');

        $app->make(ModuleDirectoryScanner::class);
    }

    #[Test]
    public function rejectsNonStringModuleDirectoryEntry(): void
    {
        $this->provider()->register();
        $app = $this->application();
        $app['config']->set('modules.paths.directories', ['app/Modules', 42]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('entry at index 1 must be a non-empty string, got [int]');

        $app->make(ModuleDirectoryScanner::class);
    }

    #[Test]
    public function rejectsEmptyStringModuleDirectoryEntry(): void
    {
        $this->provider()->register();
        $app = $this->application();
        $app['config']->set('modules.paths.directories', ['']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('entry at index 0 must be a non-empty string');

        $app->make(ModuleDirectoryScanner::class);
    }

    #[Test]
    public function brokenPathsConfigIsRejectedBeforeBuildingAnyPathService(): void
    {
        // The composition root resolves `modules.paths.*` once through the shared
        // ModulePathsConfig, so a broken config must fail the resolution of EVERY
        // path-service consumer — not just the scanner — before any of them is
        // built.
        $this->provider()->register();
        $app = $this->application();
        $app['config']->set('modules.paths.directories', 'not-an-array');

        $consumers = [
            ModuleDirectoryScanner::class,
            ModuleStatePaths::class,
            ModuleDirectoryPaths::class,
        ];

        foreach ($consumers as $consumer) {
            try {
                $app->make($consumer);
                self::fail("Resolving [{$consumer}] must reject broken modules.paths config.");
            } catch (InvalidConfigurationException $invalidConfiguration) {
                self::assertStringContainsString(
                    'must be a list of directory paths',
                    $invalidConfiguration->getMessage(),
                );
            }
        }
    }

    #[Test]
    public function runsTaggedLoadersForEnabledModulesOnceInPriorityOrder(): void
    {
        $provider = $this->provider();
        $provider->register();
        $app = $this->application();
        /** @var ArrayObject<int, array{0: string, 1: string}> $calls */
        $calls = new ArrayObject();

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
    public function registersAllDefaultLoadersAsTaggedServices(): void
    {
        $this->provider()->register();
        $app = $this->application();

        $loaderClasses = array_map(
            static fn(object $loader): string => $loader::class,
            iterator_to_array($app->tagged(ModuleLoaderServiceProvider::LOADER_TAG)),
        );

        $expected = [
            'DimitrienkoV\\LaravelModules\\Loaders\\ConfigLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\ServiceProviderLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\MigrationLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\FactoryLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\LangLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\ViewLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\BladeComponentLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\EventLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\ObserverLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\PolicyLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\CommandLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\MiddlewareLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\RouteLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\ConsoleRouteLoader',
            'DimitrienkoV\\LaravelModules\\Loaders\\BroadcastLoader',
        ];

        sort($expected);
        sort($loaderClasses);
        self::assertSame($expected, $loaderClasses);
    }

    #[Test]
    public function bootsWithoutMoonshineLoaderClass(): void
    {
        $provider = $this->provider();
        $provider->register();
        $this->application()->instance(ModuleRegistryInterface::class, new FakeRegistry([]));

        $provider->boot();

        $loaderClasses = array_map(
            static fn(object $loader): string => $loader::class,
            iterator_to_array($this->application()->tagged(ModuleLoaderServiceProvider::LOADER_TAG)),
        );

        self::assertNotContains('DimitrienkoV\\LaravelModules\\Loaders\\MoonShineLoader', $loaderClasses);
    }

    #[Test]
    public function failsLoudlyWhenTaggedLoaderDoesNotImplementContract(): void
    {
        $provider = $this->provider();
        $provider->register();
        $app = $this->application();
        $app->instance(ModuleRegistryInterface::class, new FakeRegistry([]));
        $app->instance('loader.invalid', new stdClass());
        $app->tag(['loader.invalid'], ModuleLoaderServiceProvider::LOADER_TAG);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must implement [DimitrienkoV\\LaravelModules\\Contracts\\LoaderInterface]');

        $provider->boot();
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
     * @param ArrayObject<int, array{0: string, 1: string}> $calls
     */
    public function __construct(
        private readonly ArrayObject $calls,
        private readonly int $priority,
        private readonly string $name,
    ) {}

    public function load(Module $module): LoadReport
    {
        $this->calls->append([$this->name, $module->name]);

        return LoadReport::applied();
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
    ) {}

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

        throw new RuntimeException("Module [{$name}] was not registered in fake registry.");
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

    public function reset(): void {}

}
