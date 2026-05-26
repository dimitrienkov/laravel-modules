<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Exceptions\ModuleInstallException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleRemoveException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleUpdateException;
use Illuminate\Filesystem\Filesystem;

final readonly class ModuleDirectoryOperations
{
    public function __construct(
        private Filesystem $filesystem,
        private ModuleLifecyclePaths $paths,
    ) {
    }

    public function copyDirectory(string $source, string $target): void
    {
        if (! $this->filesystem->copyDirectory($source, $target)) {
            throw ModuleInstallException::forSource($source, "failed to copy directory to [{$target}].");
        }
    }

    public function replaceDirectoryWithBackup(string $existingPath, string $replacementPath, string $moduleName): string
    {
        $backupPath = $this->paths->collisionSafeBackupPath($moduleName);
        $backupRoot = \dirname($backupPath);

        if (! is_dir($backupRoot)) {
            $this->filesystem->makeDirectory($backupRoot, 0755, true);
        }

        if (! $this->filesystem->moveDirectory($existingPath, $backupPath)) {
            throw ModuleUpdateException::forModule($moduleName, "failed to move target to backup [{$backupPath}].");
        }

        if (! $this->filesystem->copyDirectory($replacementPath, $existingPath)) {
            $this->restoreBackup($backupPath, $existingPath, $moduleName);

            throw ModuleUpdateException::forModule($moduleName, "failed to copy source to target [{$existingPath}], restored from backup.");
        }

        return $backupPath;
    }

    public function restoreBackup(string $backupPath, string $targetPath, string $moduleName): void
    {
        if (is_dir($targetPath)) {
            $this->filesystem->deleteDirectory($targetPath);
        }

        if (! $this->filesystem->moveDirectory($backupPath, $targetPath)) {
            throw ModuleUpdateException::forModule(
                $moduleName,
                "CRITICAL: failed to restore backup from [{$backupPath}] to [{$targetPath}]. Backup remains at [{$backupPath}].",
            );
        }
    }

    public function moveToBackup(string $target, string $moduleName): string
    {
        $backupPath = $this->paths->collisionSafeBackupPath($moduleName);
        $backupRoot = \dirname($backupPath);

        if (! is_dir($backupRoot)) {
            $this->filesystem->makeDirectory($backupRoot, 0755, true);
        }

        if (! $this->filesystem->moveDirectory($target, $backupPath)) {
            throw ModuleRemoveException::forModule($moduleName, "failed to move directory to backup [{$backupPath}].");
        }

        return $backupPath;
    }

    public function deleteDirectory(string $target, string $moduleName): void
    {
        if (! $this->filesystem->deleteDirectory($target)) {
            throw ModuleRemoveException::forModule($moduleName, "failed to delete directory [{$target}].");
        }
    }

    public function cleanupDirectory(string $path): void
    {
        if (is_dir($path)) {
            $this->filesystem->deleteDirectory($path);
        }
    }
}
