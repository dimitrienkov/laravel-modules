<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

final readonly class PreparedSource
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public string $path,
        public string $manifestPath,
        public array $manifest,
        public ?string $temporaryRoot,
    ) {
    }

    public function cleanup(): void
    {
        if ($this->temporaryRoot === null || ! is_dir($this->temporaryRoot)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->temporaryRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($this->temporaryRoot);
    }
}
