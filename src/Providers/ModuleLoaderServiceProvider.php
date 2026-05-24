<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Providers;

use DimitrienkoV\LaravelModules\Console\Commands\ModulesOptimizeClearCommand;
use DimitrienkoV\LaravelModules\Console\Commands\ModulesOptimizeCommand;
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
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ComposerNamespaceResolver;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

final class ModuleLoaderServiceProvider extends ServiceProvider
{
    public const string LOADER_TAG = 'laravel-modules.loaders';

    /**
     * @var array<int, string>
     */
    private const array DEFAULT_LOADERS = [
        'DimitrienkoV\\LaravelModules\\Loaders\\ConfigLoader',
        'DimitrienkoV\\LaravelModules\\Loaders\\ServiceProviderLoader',
        'DimitrienkoV\\LaravelModules\\Loaders\\MigrationLoader',
        'DimitrienkoV\\LaravelModules\\Loaders\\FactoryLoader',
        'DimitrienkoV\\LaravelModules\\Loaders\\RouteLoader',
    ];

    private const string MOONSHINE_CORE_CONTRACT = 'MoonShine\\Contracts\\Core\\DependencyInjection\\CoreContract';

    private const string MOONSHINE_LOADER = 'DimitrienkoV\\LaravelModules\\Loaders\\MoonShineLoader';

    public function register(): void
    {
        $this->mergeConfigFrom($this->packageConfigPath(), 'modules');

        $this->registerCoreBindings();
        $this->registerDefaultLoaders();
        $this->registerMoonShineLoaderWhenAvailable();
    }

    public function boot(): void
    {
        $this->publishes([
            $this->packageConfigPath() => config_path('modules.php'),
        ], 'modules-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModulesOptimizeCommand::class,
                ModulesOptimizeClearCommand::class,
            ]);

            $this->optimizes(
                optimize: 'modules:optimize',
                clear: 'modules:optimize-clear',
            );
        }

        $this->bootModules();
    }

    private function registerCoreBindings(): void
    {
        $this->app->singleton(ModuleLayout::class);
        $this->app->singleton(AtomicJsonWriter::class);
        $this->app->singleton(TopologicalSorter::class);
        $this->app->singleton(ManifestValidator::class);
        $this->app->singleton(
            ManifestValidatorInterface::class,
            fn (): ManifestValidator => $this->app->make(ManifestValidator::class),
        );

        $this->app->singleton(ComposerNamespaceResolver::class, function (): ComposerNamespaceResolver {
            return new ComposerNamespaceResolver($this->app->basePath());
        });
        $this->app->singleton(
            NamespaceResolverInterface::class,
            fn (): ComposerNamespaceResolver => $this->app->make(ComposerNamespaceResolver::class),
        );

        $this->app->singleton(ModuleManifestRepository::class, function (): ModuleManifestRepository {
            return new ModuleManifestRepository(
                layout: $this->app->make(ModuleLayout::class),
                writer: $this->app->make(AtomicJsonWriter::class),
                validator: $this->app->make(ManifestValidatorInterface::class),
                namespaceResolver: $this->app->make(NamespaceResolverInterface::class),
            );
        });
        $this->app->singleton(
            ModuleManifestRepositoryInterface::class,
            fn (): ModuleManifestRepository => $this->app->make(ModuleManifestRepository::class),
        );

        $this->app->singleton(ModuleRegistry::class, function (): ModuleRegistry {
            return new ModuleRegistry(
                config: $this->app->make(Repository::class),
                filesystem: $this->app->make(Filesystem::class),
                manifests: $this->app->make(ModuleManifestRepositoryInterface::class),
                validator: $this->app->make(ManifestValidatorInterface::class),
                sorter: $this->app->make(TopologicalSorter::class),
                layout: $this->app->make(ModuleLayout::class),
                basePath: $this->app->basePath(),
            );
        });
        $this->app->singleton(
            ModuleRegistryInterface::class,
            fn (): ModuleRegistry => $this->app->make(ModuleRegistry::class),
        );

        $this->app->scoped(FeatureRepositoryInterface::class, FeatureRepository::class);
    }

    private function registerDefaultLoaders(): void
    {
        $loaders = [];

        foreach (self::DEFAULT_LOADERS as $loader) {
            if (! class_exists($loader)) {
                continue;
            }

            $this->app->singleton($loader);
            $loaders[] = $loader;
        }

        if ($loaders !== []) {
            $this->app->tag($loaders, self::LOADER_TAG);
        }
    }

    private function registerMoonShineLoaderWhenAvailable(): void
    {
        if (
            ! interface_exists(self::MOONSHINE_CORE_CONTRACT)
            || ! class_exists(self::MOONSHINE_LOADER)
            || ! $this->app->bound(self::MOONSHINE_CORE_CONTRACT)
        ) {
            return;
        }

        $this->app->singleton(self::MOONSHINE_LOADER);
        $this->app->tag([self::MOONSHINE_LOADER], self::LOADER_TAG);
    }

    private function bootModules(): void
    {
        $registry = $this->app->make(ModuleRegistryInterface::class);
        $loaders = $this->loaders();
        $modules = $registry->loadOrder();

        foreach ($loaders as $loader) {
            foreach ($modules as $module) {
                if (! $module->isEnabled()) {
                    continue;
                }

                $loader->load($module);
            }
        }
    }

    /**
     * @return array<int, LoaderInterface>
     */
    private function loaders(): array
    {
        $loaders = [];

        foreach ($this->app->tagged(self::LOADER_TAG) as $loader) {
            if ($loader instanceof LoaderInterface) {
                $loaders[] = $loader;
            }
        }

        usort(
            $loaders,
            static fn (LoaderInterface $left, LoaderInterface $right): int => $left->priority() <=> $right->priority(),
        );

        return $loaders;
    }

    private function packageConfigPath(): string
    {
        return __DIR__ . '/../../config/modules.php';
    }
}
