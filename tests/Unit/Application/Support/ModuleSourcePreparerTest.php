<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Exceptions\ModuleSourceException;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ModuleSourcePreparerTest extends TestCase
{
    use UsesTempDirectory;

    private ModuleSourcePreparer $preparer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDirectory('source_preparer');

        $localFs = new LocalFilesystem(new Filesystem());
        $this->preparer = new ModuleSourcePreparer(
            documentReader: new ManifestDocumentReader(),
            validator: new ManifestValidator(new ManifestSettingsValidator()),
            zipExtractor: new ZipExtractor($localFs),
            filesystem: $localFs,
        );
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();
        parent::tearDown();
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
            $this->preparer->cleanup($prepared);
        }
    }

    #[Test]
    public function prepareThrowsOnDirectorySource(): void
    {
        $emptyDir = $this->tempDir . '/empty_module';
        mkdir($emptyDir, 0755, true);

        $this->expectException(ModuleSourceException::class);
        $this->expectExceptionMessageMatches('/Unsupported/');

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

        $this->preparer->cleanup($prepared);

        $this->assertDirectoryDoesNotExist($tempRoot);
    }

    #[Test]
    public function preparedSourceCleanupIsIdempotent(): void
    {
        $zipPath = $this->createModuleZip('blog');

        $prepared = $this->preparer->prepare($zipPath);
        $this->preparer->cleanup($prepared);
        $this->preparer->cleanup($prepared);

        $this->addToAssertionCount(1);
    }

    private function createModuleZip(string $name): string
    {
        $manifest = json_encode([
            'schema_version' => 1,
            'meta' => [
                'name' => $name,
                'display_name' => ucfirst($name),
                'kind' => 'module',
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

}
