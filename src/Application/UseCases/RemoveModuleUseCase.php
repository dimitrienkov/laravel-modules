<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\RemoveModuleResult;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;

final readonly class RemoveModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleStateRepositoryInterface $stateRepository,
        private ModuleDependencyGuard $dependencyGuard,
        private ModuleDirectoryOperations $directoryOps,
        private LifecycleRegistryInvalidator $invalidator,
    ) {
    }

    public function execute(string $moduleName, bool $deletePermanently = false): RemoveModuleResult
    {
        $module = $this->registry->find($moduleName);

        $this->dependencyGuard->assertCanRemove($module);

        $backupPath = null;

        if ($deletePermanently) {
            $this->directoryOps->deleteDirectory($module->path, $moduleName);
            $this->stateRepository->delete($moduleName);
        } else {
            $backupPath = $this->directoryOps->moveToBackup($module->path, $moduleName);
            $this->stateRepository->moveToBackup($moduleName, $backupPath);
        }

        $this->invalidator->invalidate();

        return new RemoveModuleResult(
            name: $moduleName,
            removedPath: $module->path,
            backupPath: $backupPath,
        );
    }
}
