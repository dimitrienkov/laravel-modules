<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use Illuminate\Filesystem\Filesystem;

final readonly class LocalFilesystem
{
    public function __construct(
        private Filesystem $filesystem,
    ) {
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->exists($path);
    }

    public function isFile(string $path): bool
    {
        return $this->filesystem->isFile($path);
    }

    public function isDirectory(string $path): bool
    {
        return $this->filesystem->isDirectory($path);
    }

    /**
     * @return list<string>
     */
    public function directories(string $directory): array
    {
        return array_values($this->filesystem->directories($directory));
    }

    public function get(string $path): string
    {
        return $this->filesystem->get($path);
    }

    public function ensureDirectoryExists(string $path, int $mode = 0755): void
    {
        $this->filesystem->ensureDirectoryExists($path, $mode);
    }

    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false): bool
    {
        return $this->filesystem->makeDirectory($path, $mode, $recursive);
    }

    public function copyFile(string $source, string $target): bool
    {
        return $this->filesystem->copy($source, $target);
    }

    public function copyDirectory(string $source, string $target): bool
    {
        return $this->filesystem->copyDirectory($source, $target);
    }

    public function moveDirectory(string $source, string $target): bool
    {
        return $this->filesystem->moveDirectory($source, $target);
    }

    public function delete(string $path): bool
    {
        return $this->filesystem->delete($path);
    }

    public function deleteFileIfExists(string $path): bool
    {
        if (! $this->filesystem->isFile($path)) {
            return true;
        }

        return $this->filesystem->delete($path);
    }

    public function deleteDirectory(string $path): bool
    {
        return $this->filesystem->deleteDirectory($path);
    }
}
