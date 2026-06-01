<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

trait UsesTempDirectory
{
    private string $tempDir;

    private function createTempDirectory(string $prefix): void
    {
        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-' . $prefix . '-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    /**
     * Join one or more path segments onto the temp root.
     */
    private function tempPath(string ...$segments): string
    {
        return $segments === []
            ? $this->tempDir
            : $this->tempDir . '/' . implode('/', $segments);
    }

    /**
     * Idempotently create a (possibly nested) directory and return its path.
     */
    private function createDirectory(string $path): string
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * Idempotently create a (possibly nested) directory under the temp root.
     */
    private function createTempSubdirectory(string ...$segments): string
    {
        return $this->createDirectory($this->tempPath(...$segments));
    }

    private function deleteTempDirectory(): void
    {
        $this->deleteDirectory($this->tempDir);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}
