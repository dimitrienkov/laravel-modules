<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Providers;

use Override;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSkeletonBuilder;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeAction;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeDto;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeQuery;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeUseCase;
use DimitrienkoV\LaravelModules\Console\Commands\Make\MakeVo;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\MakeModuleCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesDisableCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesEnableCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesInstallCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesListCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeClearCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesOptimizeCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesRemoveCommand;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesUpdateCommand;
use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\LoaderInterface;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidConfigurationException;
use DimitrienkoV\LaravelModules\Loaders\BladeComponentLoader;
use DimitrienkoV\LaravelModules\Loaders\BroadcastLoader;
use DimitrienkoV\LaravelModules\Loaders\CommandLoader;
use DimitrienkoV\LaravelModules\Loaders\ConfigLoader;
use DimitrienkoV\LaravelModules\Loaders\ConsoleRouteLoader;
use DimitrienkoV\LaravelModules\Loaders\EventLoader;
use DimitrienkoV\LaravelModules\Loaders\FactoryLoader;
use DimitrienkoV\LaravelModules\Loaders\LangLoader;
use DimitrienkoV\LaravelModules\Loaders\MiddlewareLoader;
use DimitrienkoV\LaravelModules\Loaders\MigrationLoader;
use DimitrienkoV\LaravelModules\Loaders\ObserverLoader;
use DimitrienkoV\LaravelModules\Loaders\Pipeline\ModuleLoaderPipeline;
use DimitrienkoV\LaravelModules\Loaders\PolicyLoader;
use DimitrienkoV\LaravelModules\Loaders\RouteLoader;
use DimitrienkoV\LaravelModules\Loaders\ServiceProviderLoader;
use DimitrienkoV\LaravelModules\Loaders\ViewLoader;
use DimitrienkoV\LaravelModules\Manifest\FeatureRepository;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\MoonShine\MoonShineModuleAutoloader;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistrySnapshotBuilder;
use DimitrienkoV\LaravelModules\Support\ApplicationNamespaceResolver;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\Logging\ModuleLogger;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\ModulePathsConfig;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;

final class ModuleLoaderServiceProvider extends ServiceProvider
{
    public const string LOADER_TAG = 'laravel-modules.loaders';

    /**
     * @var array<int, class-string<LoaderInterface>>
     */
    private const array DEFAULT_LOADERS = [
        ConfigLoader::class,
        ServiceProviderLoader::class,
        MigrationLoader::class,
        FactoryLoader::class,
        LangLoader::class,
        ViewLoader::class,
        BladeComponentLoader::class,
        EventLoader::class,
        ObserverLoader::class,
        PolicyLoader::class,
        CommandLoader::class,
        MiddlewareLoader::class,
        RouteLoader::class,
        ConsoleRouteLoader::class,
        BroadcastLoader::class,
    ];

    /**
     * The package's architectural generators. One source for both the singleton
     * factory bindings (they need the injected stub path) and the `commands()`
     * registration, so a new generator is declared in exactly one place.
     *
     * @var array<int, class-string>
     */
    private const array ARCHITECTURAL_GENERATORS = [
        MakeUseCase::class,
        MakeAction::class,
        MakeQuery::class,
        MakeDto::class,
        MakeVo::class,
    ];

    private const string MOONSHINE_CORE_CONTRACT = CoreContract::class;

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom($this->packageConfigPath(), 'modules');

        $this->registerPathsConfig();
        $this->registerDiagnosticsBindings();
        $this->registerManifestBindings();
        $this->registerStateBindings();
        $this->registerRegistryBindings();
        $this->registerFeatureBindings();
        $this->registerLifecycleBindings();
        $this->registerDefaultLoaders();
        $this->registerMoonShineBindings();
    }

    public function boot(): void
    {
        $this->publishes([
            $this->packageConfigPath() => config_path('modules.php'),
        ], 'modules-config');

        $this->publishes([
            $this->packageStubsPath() => base_path('stubs/modules'),
        ], 'modules-stubs');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModulesOptimizeCommand::class,
                ModulesOptimizeClearCommand::class,
                ModulesEnableCommand::class,
                ModulesDisableCommand::class,
                ModulesListCommand::class,
                MakeModuleCommand::class,
                ModulesInstallCommand::class,
                ModulesUpdateCommand::class,
                ModulesRemoveCommand::class,
            ]);

            $this->app->register(ModuleGeneratorCommandsServiceProvider::class);
            $this->registerArchitecturalGenerators();

            $this->optimizes(
                optimize: 'modules:optimize',
                clear: 'modules:optimize-clear',
            );
        }

        $this->bootMoonShineIntegration();
        $this->pipeline()->boot();
    }

    private function registerDiagnosticsBindings(): void
    {
        $this->app->singleton(
            ModuleDiagnosticsInterface::class,
            fn(): ModuleDiagnosticsInterface => $this->makeDiagnostics(),
        );
    }

    /**
     * Compose the diagnostics sink at the composition root: when logging is on,
     * a ModuleLogger over the host-configured channel; otherwise the null
     * object. Config is read through the typed Repository contract (the project
     * idiom) and passed to the logger as primitives, so ModuleLogger stays
     * decoupled from the framework config.
     */
    private function makeDiagnostics(): ModuleDiagnosticsInterface
    {
        $config = $this->app->make(Repository::class);

        if ($config->get('modules.logging.enabled', false) !== true) {
            return new NullModuleDiagnostics();
        }

        $channel = $config->get('modules.logging.channel');
        $level = $config->get('modules.logging.level', 'debug');

        return new ModuleLogger(
            logger: $this->app->make('log')->channel(\is_string($channel) ? $channel : null),
            level: \is_string($level) ? $level : 'debug',
            events: $this->normalizeEventToggles($config->get('modules.logging.events', [])),
        );
    }

    /**
     * @return array<string, bool>
     */
    private function normalizeEventToggles(mixed $events): array
    {
        if (! \is_array($events)) {
            return [];
        }

        $normalized = [];

        foreach ($events as $category => $enabled) {
            if (\is_string($category)) {
                $normalized[$category] = $enabled === true;
            }
        }

        return $normalized;
    }

    /**
     * Resolve and validate `modules.paths.*` once at the composition root and
     * share it as a singleton, so every path service is built from the same
     * scalars instead of capturing the framework config repository (an Octane
     * anti-pattern). {@see ModulePathsConfig} is the single owner of reading and
     * validating these keys.
     */
    private function registerPathsConfig(): void
    {
        $this->app->singleton(
            ModulePathsConfig::class,
            fn(): ModulePathsConfig => ModulePathsConfig::fromRepository($this->app->make(Repository::class)),
        );
    }

    private function registerManifestBindings(): void
    {
        $this->app->singleton(LocalFilesystem::class);
        $this->app->singleton(ModuleLayout::class);
        $this->app->singleton(AtomicJsonWriter::class, fn(): AtomicJsonWriter => new AtomicJsonWriter($this->app->make(AtomicFileWriter::class)));
        $this->app->singleton(ContainerLifecycleHooks::class);
        $this->app->singleton(ManifestDocumentReader::class);
        $this->app->singleton(ManifestSettingsValidator::class);
        $this->app->singleton(ManifestValidator::class);
        $this->app->singleton(
            ManifestValidatorInterface::class,
            fn(): ManifestValidator => $this->app->make(ManifestValidator::class),
        );

        $this->app->singleton(ApplicationNamespaceResolver::class);
        $this->app->singleton(
            NamespaceResolverInterface::class,
            fn(): ApplicationNamespaceResolver => $this->app->make(ApplicationNamespaceResolver::class),
        );

        $this->app->singleton(ModuleManifestRepository::class, fn(): ModuleManifestRepository => new ModuleManifestRepository(
            layout: $this->app->make(ModuleLayout::class),
            writer: $this->app->make(AtomicJsonWriter::class),
            validator: $this->app->make(ManifestValidatorInterface::class),
            namespaceResolver: $this->app->make(NamespaceResolverInterface::class),
            documentReader: $this->app->make(ManifestDocumentReader::class),
            stateRepository: $this->app->make(ModuleStateRepositoryInterface::class),
            filesystem: $this->app->make(LocalFilesystem::class),
        ));
        $this->app->singleton(
            ModuleManifestRepositoryInterface::class,
            fn(): ModuleManifestRepository => $this->app->make(ModuleManifestRepository::class),
        );
    }

    private function registerStateBindings(): void
    {
        $this->app->singleton(ModuleStatePaths::class, fn(): ModuleStatePaths => new ModuleStatePaths(
            configuredStateRoot: $this->app->make(ModulePathsConfig::class)->stateRoot(),
            basePath: $this->app->basePath(),
        ));

        $this->app->singleton(ModuleStateRepository::class, fn(): ModuleStateRepository => new ModuleStateRepository(
            paths: $this->app->make(ModuleStatePaths::class),
            writer: $this->app->make(AtomicJsonWriter::class),
            filesystem: $this->app->make(LocalFilesystem::class),
        ));
        $this->app->singleton(
            ModuleStateRepositoryInterface::class,
            fn(): ModuleStateRepository => $this->app->make(ModuleStateRepository::class),
        );
    }

    private function registerRegistryBindings(): void
    {
        $this->app->singleton(TopologicalSorter::class);

        $this->app->singleton(ModuleDirectoryScanner::class, fn(): ModuleDirectoryScanner => new ModuleDirectoryScanner(
            directories: $this->app->make(ModulePathsConfig::class)->directories(),
            filesystem: $this->app->make(LocalFilesystem::class),
            layout: $this->app->make(ModuleLayout::class),
            basePath: $this->app->basePath(),
            appPath: $this->app->path(),
            diagnostics: $this->app->make(ModuleDiagnosticsInterface::class),
        ));

        $this->app->singleton(ModuleRegistryCache::class, fn(): ModuleRegistryCache => new ModuleRegistryCache(
            validator: $this->app->make(ManifestValidatorInterface::class),
            layout: $this->app->make(ModuleLayout::class),
            stateRepository: $this->app->make(ModuleStateRepositoryInterface::class),
            basePath: $this->app->basePath(),
            diagnostics: $this->app->make(ModuleDiagnosticsInterface::class),
        ));

        $this->app->singleton(
            ModuleRegistryCacheInterface::class,
            fn(): ModuleRegistryCache => $this->app->make(ModuleRegistryCache::class),
        );

        $this->app->singleton(ModuleRegistrySnapshotBuilder::class, fn(): ModuleRegistrySnapshotBuilder => new ModuleRegistrySnapshotBuilder(
            scanner: $this->app->make(ModuleDirectoryScanner::class),
            manifests: $this->app->make(ModuleManifestRepositoryInterface::class),
            sorter: $this->app->make(TopologicalSorter::class),
            diagnostics: $this->app->make(ModuleDiagnosticsInterface::class),
        ));

        $this->app->singleton(ModuleRegistry::class, fn(): ModuleRegistry => new ModuleRegistry(
            builder: $this->app->make(ModuleRegistrySnapshotBuilder::class),
            cache: $this->app->make(ModuleRegistryCacheInterface::class),
            diagnostics: $this->app->make(ModuleDiagnosticsInterface::class),
        ));
        $this->app->singleton(
            ModuleRegistryInterface::class,
            fn(): ModuleRegistry => $this->app->make(ModuleRegistry::class),
        );
    }

    private function registerFeatureBindings(): void
    {
        $this->app->scoped(FeatureRepositoryInterface::class, FeatureRepository::class);
    }

    private function registerLifecycleBindings(): void
    {
        $this->app->singleton(ZipExtractor::class);
        $this->app->singleton(ModuleSourcePreparer::class);

        $this->app->singleton(ModuleDirectoryPaths::class, fn(): ModuleDirectoryPaths => new ModuleDirectoryPaths(
            directories: $this->app->make(ModulePathsConfig::class)->directories(),
            basePath: $this->app->basePath(),
            appPath: $this->app->path(),
            configuredBackupRoot: $this->app->make(ModulePathsConfig::class)->backupRoot(),
        ));

        $this->app->singleton(LifecycleRegistryInvalidator::class);
        $this->app->singleton(ModuleDependencyGuard::class);
        $this->app->singleton(ModuleDirectoryOperations::class);
        $this->app->singleton(AtomicFileWriter::class);
        $this->app->singleton(ModuleSkeletonBuilder::class, fn(): ModuleSkeletonBuilder => new ModuleSkeletonBuilder(
            $this->app->make(LocalFilesystem::class),
            $this->app->make(AtomicFileWriter::class),
            stubsPath: $this->packageStubsPath(),
        ));
    }

    private function registerDefaultLoaders(): void
    {
        foreach (self::DEFAULT_LOADERS as $loader) {
            $this->app->singleton($loader);
        }

        $this->app->tag(self::DEFAULT_LOADERS, self::LOADER_TAG);
    }

    private function registerMoonShineBindings(): void
    {
        if (! interface_exists(self::MOONSHINE_CORE_CONTRACT)) {
            return;
        }

        $this->app->singleton(MoonShineModuleAutoloader::class);
    }

    private function bootMoonShineIntegration(): void
    {
        if (! interface_exists(self::MOONSHINE_CORE_CONTRACT)) {
            return;
        }

        $this->app->make(ContainerLifecycleHooks::class)->callAfterResolving(
            self::MOONSHINE_CORE_CONTRACT,
            function (object $core): void {
                if (! $core instanceof CoreContract) {
                    return;
                }

                $this->app->make(MoonShineModuleAutoloader::class)->autoload($core);
            },
        );
    }

    /**
     * @return array<int, LoaderInterface>
     */
    private function resolveTaggedLoaders(): array
    {
        $loaders = [];
        foreach ($this->app->tagged(self::LOADER_TAG) as $tagged) {
            if (! $tagged instanceof LoaderInterface) {
                throw InvalidConfigurationException::forKey(
                    self::LOADER_TAG,
                    \sprintf(
                        'tagged service [%s] must implement [%s].',
                        get_debug_type($tagged),
                        LoaderInterface::class,
                    ),
                );
            }

            $loaders[] = $tagged;
        }

        return $loaders;
    }

    private function pipeline(): ModuleLoaderPipeline
    {
        return new ModuleLoaderPipeline(
            registry: $this->app->make(ModuleRegistryInterface::class),
            loaders: $this->resolveTaggedLoaders(),
            exceptionHandler: $this->app->make(ExceptionHandler::class),
            diagnostics: $this->app->make(ModuleDiagnosticsInterface::class),
        );
    }

    /**
     * Register the package's architectural generators. They extend Laravel's
     * GeneratorCommand (which only takes a Filesystem), so the package stub path
     * is injected here at the composition root — keeping `file_exists()`-style
     * stub resolution, a forbidden filesystem token, out of `src/`.
     */
    private function registerArchitecturalGenerators(): void
    {
        $stubsPath = $this->packageStubsPath();

        foreach (self::ARCHITECTURAL_GENERATORS as $generator) {
            $this->app->singleton(
                $generator,
                static fn(Application $app): object => new $generator($app->make(Filesystem::class), $stubsPath),
            );
        }

        $this->commands(self::ARCHITECTURAL_GENERATORS);
    }

    private function packageConfigPath(): string
    {
        return __DIR__ . '/../../config/modules.php';
    }

    private function packageStubsPath(): string
    {
        return \dirname(__DIR__, 2) . '/stubs';
    }
}
