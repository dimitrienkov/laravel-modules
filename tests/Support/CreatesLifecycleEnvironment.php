<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistrySnapshotBuilder;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;

/**
 * Using class must define: private string $tempDir; private string $stateRoot;
 */
trait CreatesLifecycleEnvironment
{
    /**
     * @param array<int, string> $directories
     */
    protected function lifecycleConfig(
        array $directories = ['app/Modules'],
        ?string $backupPath = null,
    ): Repository {
        $paths = [
            'directories' => $directories,
            'state' => $this->stateRoot,
        ];

        if ($backupPath !== null) {
            $paths['backup'] = $backupPath;
        }

        return new Repository(['modules' => ['paths' => $paths]]);
    }

    protected function lifecycleStateRepository(Repository $config): ModuleStateRepository
    {
        return new ModuleStateRepository(
            paths: new ModuleStatePaths(config: $config, basePath: $this->tempDir),
            writer: new AtomicJsonWriter(),
            filesystem: new LocalFilesystem(new Filesystem()),
        );
    }

    protected function lifecycleManifestRepository(ModuleStateRepository $stateRepo): ModuleManifestRepository
    {
        return new ModuleManifestRepository(
            layout: new ModuleLayout(),
            writer: new AtomicJsonWriter(),
            validator: new ManifestValidator(new ManifestSettingsValidator()),
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
            documentReader: new ManifestDocumentReader(),
            stateRepository: $stateRepo,
            filesystem: new LocalFilesystem(new Filesystem()),
        );
    }

    protected function lifecycleRegistryCache(ModuleStateRepository $stateRepo): ModuleRegistryCache
    {
        return new ModuleRegistryCache(
            validator: new ManifestValidator(new ManifestSettingsValidator()),
            layout: new ModuleLayout(),
            stateRepository: $stateRepo,
            basePath: $this->tempDir,
        );
    }

    protected function lifecycleSnapshotBuilder(
        ModuleManifestRepository $manifests,
        Repository $config,
    ): ModuleRegistrySnapshotBuilder {
        return new ModuleRegistrySnapshotBuilder(
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new LocalFilesystem(new Filesystem()),
                layout: new ModuleLayout(),
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            manifests: $manifests,
            sorter: new TopologicalSorter(),
        );
    }

    protected function lifecycleRegistry(
        ModuleManifestRepository $manifests,
        ModuleStateRepository $stateRepo,
        Repository $config,
    ): ModuleRegistry {
        return new ModuleRegistry(
            builder: $this->lifecycleSnapshotBuilder($manifests, $config),
            cache: $this->lifecycleRegistryCache($stateRepo),
        );
    }

    protected function lifecycleDirectoryPaths(Repository $config): ModuleDirectoryPaths
    {
        return new ModuleDirectoryPaths($config, $this->tempDir, $this->tempDir . '/app');
    }

    protected function lifecycleDirectoryOps(ModuleDirectoryPaths $paths): ModuleDirectoryOperations
    {
        return new ModuleDirectoryOperations(new LocalFilesystem(new Filesystem()), $paths);
    }

    protected function lifecycleDependencyGuard(ModuleRegistry $registry): ModuleDependencyGuard
    {
        return new ModuleDependencyGuard($registry, new TopologicalSorter());
    }

    protected function lifecycleInvalidator(ModuleRegistryCache $cache, ModuleRegistry $registry): LifecycleRegistryInvalidator
    {
        return new LifecycleRegistryInvalidator($cache, $registry);
    }

    protected function lifecycleSourcePreparer(): ModuleSourcePreparer
    {
        $filesystem = new LocalFilesystem(new Filesystem());

        return new ModuleSourcePreparer(
            new ManifestDocumentReader(),
            new ManifestValidator(new ManifestSettingsValidator()),
            new ZipExtractor($filesystem),
            $filesystem,
        );
    }

    protected function lifecycleNamespaceResolver(): FakeNamespaceResolver
    {
        return new FakeNamespaceResolver($this->tempDir);
    }
}
