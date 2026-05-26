<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\RemoveModuleResult;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\DirectoryOperationException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleRemoveException;

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
            try {
                $this->directoryOps->deleteDirectory($module->path, $moduleName);
            } catch (DirectoryOperationException $e) {
                throw ModuleRemoveException::forModule($moduleName, $e->getMessage(), $e);
            }

            try {
                $this->stateRepository->delete($moduleName);
            } catch (\Throwable $e) {
                throw ModuleRemoveException::forModule(
                    $moduleName,
                    'directory deleted but state cleanup failed. State may be orphaned.',
                    $e,
                );
            }
        } else {
            try {
                $backupPath = $this->directoryOps->moveToBackup($module->path, $moduleName);
            } catch (DirectoryOperationException $e) {
                throw ModuleRemoveException::forModule($moduleName, $e->getMessage(), $e);
            }

            try {
                $this->stateRepository->moveToBackup($moduleName, $backupPath);
            } catch (\Throwable $e) {
                try {
                    $this->directoryOps->restoreBackup($backupPath, $module->path, $moduleName);
                } catch (\Throwable $restoreError) {
                    throw ModuleRemoveException::forModule(
                        $moduleName,
                        "state backup failed and restore also failed. Module backup at [{$backupPath}]. Restore error: {$restoreError->getMessage()}",
                        $e,
                    );
                }

                throw ModuleRemoveException::forModule(
                    $moduleName,
                    'state backup failed, restored module from backup.',
                    $e,
                );
            }
        }

        $this->invalidator->invalidate();

        return new RemoveModuleResult(
            name: $moduleName,
            removedPath: $module->path,
            backupPath: $backupPath,
        );
    }
}
