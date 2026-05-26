<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Exceptions\DirectoryOperationException;
use DimitrienkoV\LaravelModules\Support\ModulePermissions;
use Illuminate\Filesystem\Filesystem;

final readonly class ModuleDirectoryOperations
{
    public function __construct(
        private Filesystem $filesystem,
        private ModuleDirectoryPaths $paths,
    ) {
    }

    public function copyDirectory(string $source, string $target): void
    {
        if (! $this->filesystem->copyDirectory($source, $target)) {
            throw DirectoryOperationException::forPath($source, "failed to copy directory to [{$target}].");
        }
    }

    public function replaceDirectoryWithBackup(string $existingPath, string $replacementPath, string $moduleName): string
    {
        $backupPath = $this->paths->collisionSafeBackupPath($moduleName);
        $backupRoot = \dirname($backupPath);

        if (! is_dir($backupRoot)) {
            $this->filesystem->makeDirectory($backupRoot, ModulePermissions::DIRECTORY, true);
        }

        if (! $this->filesystem->moveDirectory($existingPath, $backupPath)) {
            throw DirectoryOperationException::forPath($existingPath, "failed to move target to backup [{$backupPath}].");
        }

        if (! $this->filesystem->copyDirectory($replacementPath, $existingPath)) {
            $this->restoreBackup($backupPath, $existingPath, $moduleName);

            throw DirectoryOperationException::forPath($replacementPath, "failed to copy source to target [{$existingPath}], restored from backup.");
        }

        return $backupPath;
    }

    public function restoreBackup(string $backupPath, string $targetPath, string $moduleName): void
    {
        if (is_dir($targetPath)) {
            $this->filesystem->deleteDirectory($targetPath);
        }

        if (! $this->filesystem->moveDirectory($backupPath, $targetPath)) {
            throw DirectoryOperationException::forPath(
                $backupPath,
                "CRITICAL: failed to restore backup to [{$targetPath}]. Backup remains at [{$backupPath}].",
            );
        }
    }

    public function moveToBackup(string $target, string $moduleName): string
    {
        $backupPath = $this->paths->collisionSafeBackupPath($moduleName);
        $backupRoot = \dirname($backupPath);

        if (! is_dir($backupRoot)) {
            $this->filesystem->makeDirectory($backupRoot, ModulePermissions::DIRECTORY, true);
        }

        if (! $this->filesystem->moveDirectory($target, $backupPath)) {
            throw DirectoryOperationException::forPath($target, "failed to move directory to backup [{$backupPath}].");
        }

        return $backupPath;
    }

    public function deleteDirectory(string $target, string $moduleName): void
    {
        if (! $this->filesystem->deleteDirectory($target)) {
            throw DirectoryOperationException::forPath($target, 'failed to delete directory.');
        }
    }

    public function deleteDirectoryQuietly(string $path): bool
    {
        if (! is_dir($path)) {
            return true;
        }

        return $this->filesystem->deleteDirectory($path);
    }
}
