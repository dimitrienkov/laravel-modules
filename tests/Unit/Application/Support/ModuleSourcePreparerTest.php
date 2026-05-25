<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Exceptions\ModuleSourceException;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ModuleSourcePreparerTest extends TestCase
{
    private string $tempDir;

    private ModuleSourcePreparer $preparer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/source_preparer_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->preparer = new ModuleSourcePreparer(
            documentReader: new ManifestDocumentReader(),
            validator: new ManifestValidator(new ManifestSettingsValidator()),
            zipExtractor: new ZipExtractor(),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function prepareFromDirectorySource(): void
    {
        $sourceDir = $this->createModuleDirectory('blog');

        $prepared = $this->preparer->prepare($sourceDir);

        try {
            $this->assertSame($sourceDir, $prepared->path);
            $this->assertNull($prepared->temporaryRoot);
            $this->assertSame('blog', $prepared->manifest['meta']['name']);
        } finally {
            $prepared->cleanup();
        }
    }

    #[Test]
    public function prepareFromZipSource(): void
    {
        $zipPath = $this->createModuleZip('blog');

        $prepared = $this->preparer->prepare($zipPath);

        try {
            $this->assertNotNull($prepared->temporaryRoot);
            $this->assertDirectoryExists($prepared->path);
            $this->assertSame('blog', $prepared->manifest['meta']['name']);
        } finally {
            $prepared->cleanup();
        }
    }

    #[Test]
    public function prepareThrowsOnMissingManifestInDirectory(): void
    {
        $emptyDir = $this->tempDir . '/empty_module';
        mkdir($emptyDir, 0755, true);

        $this->expectException(ModuleSourceException::class);
        $this->expectExceptionMessageMatches('/module\.json not found/');

        $this->preparer->prepare($emptyDir);
    }

    #[Test]
    public function prepareThrowsOnMissingManifestInZip(): void
    {
        $zipPath = $this->tempDir . '/no_manifest.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'no manifest here');
        $zip->close();

        $this->expectException(ModuleSourceException::class);
        $this->expectExceptionMessageMatches('/module\.json not found/');

        $this->preparer->prepare($zipPath);
    }

    #[Test]
    public function prepareThrowsOnUnsupportedSourceType(): void
    {
        $tarPath = $this->tempDir . '/module.tar.gz';
        file_put_contents($tarPath, 'fake tar');

        $this->expectException(ModuleSourceException::class);
        $this->expectExceptionMessageMatches('/Unsupported/');

        $this->preparer->prepare($tarPath);
    }

    #[Test]
    public function preparedSourceCleanupRemovesTemporaryDirectory(): void
    {
        $zipPath = $this->createModuleZip('blog');

        $prepared = $this->preparer->prepare($zipPath);
        $tempRoot = $prepared->temporaryRoot;
        $this->assertNotNull($tempRoot);
        $this->assertDirectoryExists($tempRoot);

        $prepared->cleanup();

        $this->assertDirectoryDoesNotExist($tempRoot);
    }

    #[Test]
    public function preparedSourceCleanupIsIdempotent(): void
    {
        $zipPath = $this->createModuleZip('blog');

        $prepared = $this->preparer->prepare($zipPath);
        $prepared->cleanup();
        $prepared->cleanup();

        $this->addToAssertionCount(1);
    }

    private function createModuleDirectory(string $name): string
    {
        $dir = $this->tempDir . '/' . ucfirst($name);
        mkdir($dir, 0755, true);

        $manifest = [
            'meta' => [
                'name' => $name,
                'display_name' => ucfirst($name),
                'version' => '1.0.0',
            ],
            'settings' => [
                'schema' => new \stdClass(),
            ],
        ];

        file_put_contents(
            $dir . '/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        return $dir;
    }

    private function createModuleZip(string $name): string
    {
        $manifest = json_encode([
            'meta' => [
                'name' => $name,
                'display_name' => ucfirst($name),
                'version' => '1.0.0',
            ],
            'settings' => [
                'schema' => new \stdClass(),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $zipPath = $this->tempDir . '/' . $name . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('module.json', $manifest);
        $zip->close();

        return $zipPath;
    }

    private function removeDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
