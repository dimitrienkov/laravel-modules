<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Application\Enums\ModuleSourceKind;
use Illuminate\Filesystem\Filesystem;

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
        private Filesystem $filesystem,
    ) {
    }

    public function moduleName(): string
    {
        return $this->manifest['meta']['name'];
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->manifest['meta'];
    }

    public function cleanup(): void
    {
        if ($this->temporaryRoot !== null && is_dir($this->temporaryRoot)) {
            $this->filesystem->deleteDirectory($this->temporaryRoot);
        }
    }
}
