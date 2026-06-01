<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Application\Enums\ModuleSourceKind;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleSourceException;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\VO\Checksum;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleFileNames;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use Throwable;

final readonly class ModuleSourcePreparer
{
    public function __construct(
        private ManifestDocumentReader $documentReader,
        private ManifestValidatorInterface $validator,
        private ZipExtractor $zipExtractor,
        private LocalFilesystem $filesystem,
    ) {
    }

    public function prepare(string $sourcePath): PreparedSource
    {
        if ($this->filesystem->isFile($sourcePath) && str_ends_with(strtolower($sourcePath), '.zip')) {
            return $this->prepareFromZip($sourcePath);
        }

        throw ModuleSourceException::unsupportedType($sourcePath);
    }

    public function cleanup(PreparedSource $source): void
    {
        if ($source->temporaryRoot === null) {
            return;
        }

        try {
            $this->filesystem->deleteDirectory($source->temporaryRoot);
        } catch (Throwable) {
            // best-effort: temp dir removal failure must not mask the primary outcome
        }
    }

    private function prepareFromZip(string $sourcePath): PreparedSource
    {
        $checksum = $this->checksumForArchive($sourcePath);

        $tempDir = $this->zipExtractor->extractToTemp($sourcePath);

        try {
            $manifestPath = $tempDir . '/' . ModuleFileNames::MANIFEST;

            if (! $this->filesystem->isFile($manifestPath)) {
                throw ModuleSourceException::forPath($sourcePath, 'module.json not found in archive.');
            }

            $this->assertNoStateFile($tempDir);

            $manifest = $this->documentReader->read($manifestPath);
            $this->validator->validate($manifest, $manifestPath);

            return new PreparedSource(
                path: $tempDir,
                manifestPath: $manifestPath,
                manifest: $manifest,
                temporaryRoot: $tempDir,
                sourceKind: ModuleSourceKind::Zip,
                checksum: $checksum,
            );
        } catch (Throwable $e) {
            // Cleanup must not mask the primary failure: a throwing deleteDirectory
            // would otherwise replace the original manifest/source validation error.
            try {
                $this->filesystem->deleteDirectory($tempDir);
            } catch (Throwable) {
            }

            throw $e;
        }
    }

    private function checksumForArchive(string $sourcePath): Checksum
    {
        $hash = $this->filesystem->hashFile($sourcePath, Checksum::ALGORITHM);

        if ($hash === false) {
            throw ModuleSourceException::forPath($sourcePath, 'failed to compute checksum for archive.');
        }

        return new Checksum($hash);
    }

    private function assertNoStateFile(string $sourceRoot): void
    {
        if ($this->filesystem->isFile($sourceRoot . '/' . ModuleFileNames::STATE)) {
            throw ModuleSourceException::forPath(
                $sourceRoot,
                'source contains state.json which belongs to host private storage, not module artifact.',
            );
        }
    }
}
