<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Application\Enums\ModuleSourceKind;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleSourceException;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Support\ModuleFileNames;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use Illuminate\Filesystem\Filesystem;

final readonly class ModuleSourcePreparer
{
    public function __construct(
        private ManifestDocumentReader $documentReader,
        private ManifestValidatorInterface $validator,
        private ZipExtractor $zipExtractor,
        private Filesystem $filesystem,
    ) {
    }

    public function prepare(string $sourcePath): PreparedSource
    {
        if (is_dir($sourcePath)) {
            return $this->prepareFromDirectory($sourcePath);
        }

        if (is_file($sourcePath) && str_ends_with(strtolower($sourcePath), '.zip')) {
            return $this->prepareFromZip($sourcePath);
        }

        throw ModuleSourceException::unsupportedType($sourcePath);
    }

    private function prepareFromDirectory(string $sourcePath): PreparedSource
    {
        $manifestPath = $sourcePath . '/' . ModuleFileNames::MANIFEST;

        if (! is_file($manifestPath)) {
            throw ModuleSourceException::forPath($sourcePath, 'module.json not found in source directory.');
        }

        $this->rejectStateFile($sourcePath);

        $manifest = $this->documentReader->read($manifestPath);
        $this->validator->validate($manifest, $manifestPath);

        return new PreparedSource(
            path: $sourcePath,
            manifestPath: $manifestPath,
            manifest: $manifest,
            temporaryRoot: null,
            sourceKind: ModuleSourceKind::Directory,
            filesystem: $this->filesystem,
        );
    }

    private function prepareFromZip(string $sourcePath): PreparedSource
    {
        $tempDir = $this->zipExtractor->extractToTemp($sourcePath);

        try {
            $manifestPath = $tempDir . '/' . ModuleFileNames::MANIFEST;

            if (! is_file($manifestPath)) {
                throw ModuleSourceException::forPath($sourcePath, 'module.json not found in archive.');
            }

            $this->rejectStateFile($tempDir);

            $manifest = $this->documentReader->read($manifestPath);
            $this->validator->validate($manifest, $manifestPath);

            return new PreparedSource(
                path: $tempDir,
                manifestPath: $manifestPath,
                manifest: $manifest,
                temporaryRoot: $tempDir,
                sourceKind: ModuleSourceKind::Zip,
                filesystem: $this->filesystem,
            );
        } catch (\Throwable $e) {
            $this->filesystem->deleteDirectory($tempDir);

            throw $e;
        }
    }

    private function rejectStateFile(string $sourceRoot): void
    {
        if (is_file($sourceRoot . '/' . ModuleFileNames::STATE)) {
            throw ModuleSourceException::forPath(
                $sourceRoot,
                'source contains state.json which belongs to host private storage, not module artifact.',
            );
        }
    }
}
