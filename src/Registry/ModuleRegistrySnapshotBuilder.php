<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Registry;

use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Registry\VO\ModuleRegistrySnapshot;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;

final readonly class ModuleRegistrySnapshotBuilder
{
    public function __construct(
        private ModuleDirectoryScanner $scanner,
        private ModuleManifestRepositoryInterface $manifests,
        private TopologicalSorter $sorter,
    ) {
    }

    public function build(): ModuleRegistrySnapshot
    {
        $modules = [];

        foreach ($this->scanner->scan() as $modulePath) {
            $modules[] = $this->manifests->load($modulePath);
        }

        $sorted = $this->sorter->sort($modules);

        return new ModuleRegistrySnapshot($sorted);
    }
}
