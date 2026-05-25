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
