<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Support;

trait CreatesSourceArchive
{
    /**
     * Build a source zip containing a `module.json` entry for the given manifest.
     *
     * The parent directory is created if missing. Additional raw entries may be
     * supplied as `entry name => contents` for scenarios that need extra files
     * alongside the manifest (e.g. a forbidden `state.json`).
     *
     * @param array<string, mixed>  $manifest
     * @param array<string, string> $extraEntries
     */
    protected function zipModuleSource(string $zipPath, array $manifest, array $extraEntries = []): string
    {
        $directory = \dirname($zipPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString(
            'module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        foreach ($extraEntries as $entryName => $contents) {
            $zip->addFromString($entryName, $contents);
        }

        $zip->close();

        return $zipPath;
    }
}
