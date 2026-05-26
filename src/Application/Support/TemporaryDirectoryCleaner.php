<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

final readonly class TemporaryDirectoryCleaner
{
    public function cleanup(?string $directory): void
    {
        if ($directory === null || ! is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}
