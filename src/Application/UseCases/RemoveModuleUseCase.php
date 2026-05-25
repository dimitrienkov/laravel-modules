<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\RemoveModuleResult;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;

final readonly class RemoveModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleDependencyGuard $dependencyGuard,
        private ModuleDirectoryOperations $directoryOps,
        private LifecycleRegistryInvalidator $invalidator,
    ) {
    }

    public function execute(string $moduleName, bool $noBackup = false): RemoveModuleResult
    {
        $module = $this->registry->find($moduleName);

        $this->dependencyGuard->assertCanRemove($module);

        $backupPath = null;

        if ($noBackup) {
            $this->directoryOps->deleteDirectory($module->path, $moduleName);
        } else {
            $backupPath = $this->directoryOps->moveToBackup($module->path, $moduleName);
        }

        $this->invalidator->invalidate();

        return new RemoveModuleResult(
            name: $moduleName,
            removedPath: $module->path,
            backupPath: $backupPath,
        );
    }
}
