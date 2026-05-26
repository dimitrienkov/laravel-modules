<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Application\Enums\ModuleSourceKind;
use DimitrienkoV\LaravelModules\Exceptions\ModuleSourceException;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;

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
        public ModuleSourceKind $sourceKind,
        private LocalFilesystem $filesystem,
    ) {
    }

    public function moduleName(): string
    {
        if (
            ! \is_array($this->manifest['meta'] ?? null)
            || ! \is_string($this->manifest['meta']['name'] ?? null)
            || trim($this->manifest['meta']['name']) === ''
        ) {
            throw ModuleSourceException::forPath(
                $this->manifestPath,
                'manifest is missing a valid meta.name string.',
            );
        }

        return $this->manifest['meta']['name'];
    }

    public function cleanup(): void
    {
        if ($this->temporaryRoot === null) {
            return;
        }

        try {
            $this->filesystem->deleteDirectory($this->temporaryRoot);
        } catch (\Throwable) {
        }
    }
}
