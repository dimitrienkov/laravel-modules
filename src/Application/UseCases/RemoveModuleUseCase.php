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
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Throwable;

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

        $backupPath = $deletePermanently
            ? $this->removePermanently($module)
            : $this->removeWithBackup($module);

        $this->invalidator->flushAndReset();

        return new RemoveModuleResult(
            name: $moduleName,
            removedPath: $module->path,
            backupPath: $backupPath,
        );
    }

    private function removePermanently(Module $module): null
    {
        $existingStateDocument = $this->stateRepository->read($module->name, $module);

        try {
            $this->stateRepository->delete($module->name);
        } catch (Throwable $e) {
            throw ModuleRemoveException::forModule(
                $module->name,
                'state deletion failed, module directory was not touched.',
                $e,
            );
        }

        try {
            $this->directoryOps->deleteDirectory($module->path);
        } catch (DirectoryOperationException $e) {
            try {
                $this->stateRepository->writeDocument($module->name, $existingStateDocument);
            } catch (Throwable $restoreError) {
                throw ModuleRemoveException::forModule(
                    $module->name,
                    "state deleted and directory removal failed, state restore also failed. Orphaned directory at [{$module->path}]. Restore error: {$restoreError->getMessage()}",
                    $restoreError,
                );
            }

            throw ModuleRemoveException::forModule(
                $module->name,
                "directory removal failed, restored state. Directory at [{$module->path}].",
                $e,
            );
        }

        return null;
    }

    private function removeWithBackup(Module $module): string
    {
        try {
            $backupPath = $this->directoryOps->moveToBackup($module->path, $module->name);
        } catch (DirectoryOperationException $e) {
            throw ModuleRemoveException::forModule($module->name, $e->getMessage(), $e);
        }

        try {
            $this->stateRepository->moveToBackup($module->name, $backupPath);
        } catch (Throwable $e) {
            try {
                $this->directoryOps->restoreBackup($backupPath, $module->path);
            } catch (Throwable $restoreError) {
                throw ModuleRemoveException::forModule(
                    $module->name,
                    "state backup failed and restore also failed. Module backup at [{$backupPath}]. Original error: {$e->getMessage()}. Restore error: {$restoreError->getMessage()}",
                    $restoreError,
                );
            }

            throw ModuleRemoveException::forModule(
                $module->name,
                'state backup failed, restored module from backup.',
                $e,
            );
        }

        return $backupPath;
    }
}
