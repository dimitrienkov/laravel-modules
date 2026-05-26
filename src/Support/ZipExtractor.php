<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Exceptions\ModuleArchiveException;
use Illuminate\Filesystem\Filesystem;
use ZipArchive;

final readonly class ZipExtractor
{
    public function __construct(
        private Filesystem $filesystem,
    ) {
    }

    public function extract(string $zipPath, string $targetDir): void
    {
        $this->assertExtensionLoaded();

        $zip = new ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw ModuleArchiveException::forPath($zipPath, "failed to open archive (error code: {$result}).");
        }

        try {
            $this->assertNoPathTraversal($zip, $zipPath);

            if (! is_dir($targetDir) && ! $this->filesystem->makeDirectory($targetDir, ModulePermissions::DIRECTORY, true)) {
                throw ModuleArchiveException::forPath($zipPath, "failed to create target directory [{$targetDir}].");
            }

            if (! $zip->extractTo($targetDir)) {
                throw ModuleArchiveException::forPath($zipPath, "failed to extract archive to [{$targetDir}].");
            }
        } finally {
            $zip->close();
        }
    }

    public function extractToTemp(string $zipPath): string
    {
        $tempDir = sys_get_temp_dir() . '/module_zip_' . bin2hex(random_bytes(8));

        try {
            $this->extract($zipPath, $tempDir);
        } catch (ModuleArchiveException $e) {
            if (is_dir($tempDir)) {
                $this->filesystem->deleteDirectory($tempDir);
            }

            throw $e;
        }

        return $tempDir;
    }

    private function assertExtensionLoaded(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw ModuleArchiveException::extensionMissing();
        }
    }

    private function assertNoPathTraversal(ZipArchive $zip, string $zipPath): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false) {
                continue;
            }

            $normalized = str_replace('\\', '/', $entryName);

            if (
                str_contains($entryName, "\0")
                || str_contains($normalized, '../')
                || str_starts_with($normalized, '/')
                || preg_match('/^[A-Za-z]:/', $normalized)
            ) {
                throw ModuleArchiveException::zipSlip($zipPath, $entryName);
            }
        }
    }
}
