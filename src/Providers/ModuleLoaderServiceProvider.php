<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Providers;

use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeClearCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeCommand;
use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Loaders\Pipeline\ModuleLoaderPipeline;
use DimitrienkoV\LaravelModules\Manifest\FeatureRepository;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\MoonShine\MoonShineModuleAutoloader;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ComposerNamespaceResolver;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;

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

    private const string MOONSHINE_CORE_CONTRACT = 'MoonShine\\Contracts\\Core\\DependencyInjection\\CoreContract';

    public function register(): void
    {
        $this->mergeConfigFrom($this->packageConfigPath(), 'modules');

        $this->registerCoreBindings();
        $this->registerDefaultLoaders();
        $this->registerMoonShineIntegration();
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

        $this->pipeline()->boot();
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

        $this->app->singleton(ModuleDirectoryScanner::class, function (): ModuleDirectoryScanner {
            return new ModuleDirectoryScanner(
                config: $this->app->make(Repository::class),
                filesystem: $this->app->make(Filesystem::class),
                layout: $this->app->make(ModuleLayout::class),
                basePath: $this->app->basePath(),
            );
        });

        $this->app->singleton(ModuleRegistryCache::class, function (): ModuleRegistryCache {
            return new ModuleRegistryCache(
                validator: $this->app->make(ManifestValidatorInterface::class),
                layout: $this->app->make(ModuleLayout::class),
                basePath: $this->app->basePath(),
            );
        });

        $this->app->singleton(ModuleRegistry::class, function (): ModuleRegistry {
            return new ModuleRegistry(
                manifests: $this->app->make(ModuleManifestRepositoryInterface::class),
                sorter: $this->app->make(TopologicalSorter::class),
                scanner: $this->app->make(ModuleDirectoryScanner::class),
                cache: $this->app->make(ModuleRegistryCache::class),
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

    private function registerMoonShineIntegration(): void
    {
        if (! interface_exists(self::MOONSHINE_CORE_CONTRACT)) {
            return;
        }

        $this->app->singleton(MoonShineModuleAutoloader::class);

        $this->app->afterResolving(
            self::MOONSHINE_CORE_CONTRACT,
            function (object $core): void {
                /** @var CoreContract $core */
                $this->app->make(MoonShineModuleAutoloader::class)->autoload($core);
            },
        );
    }

    private function pipeline(): ModuleLoaderPipeline
    {
        $loaders = [];
        foreach ($this->app->tagged(self::LOADER_TAG) as $tagged) {
            if ($tagged instanceof LoaderInterface) {
                $loaders[] = $tagged;
            }
        }

        return new ModuleLoaderPipeline(
            registry: $this->app->make(ModuleRegistryInterface::class),
            loaders: $loaders,
        );
    }

    private function packageConfigPath(): string
    {
        return __DIR__ . '/../../config/modules.php';
    }
}
