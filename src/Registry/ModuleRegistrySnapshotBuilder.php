<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry;

use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Registry\VO\ModuleRegistrySnapshot;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;

final readonly class ModuleRegistrySnapshotBuilder
{
    public function __construct(
        private ModuleDirectoryScanner $scanner,
        private ModuleManifestRepositoryInterface $manifests,
        private TopologicalSorter $sorter,
        private ModuleDiagnosticsInterface $diagnostics = new NullModuleDiagnostics(),
    ) {}

    public function build(): ModuleRegistrySnapshot
    {
        $modules = [];

        foreach ($this->scanner->scan() as $modulePath) {
            $module = $this->manifests->load($modulePath);
            $modules[] = $module;
            $this->diagnostics->discoveryModuleFound($module->name, $module->path);
        }

        $sorted = $this->sorter->sort($modules);

        $enabled = \count(array_filter($modules, static fn(Module $module): bool => $module->isEnabled()));
        $this->diagnostics->discoveryCompleted(\count($modules), $enabled, \count($modules) - $enabled);

        return new ModuleRegistrySnapshot($sorted);
    }
}
