<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\ModuleArchiveException;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ZipExtractorTest extends TestCase
{
    use UsesTempDirectory;

    private ZipExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDirectory('zip_extractor');
        $this->extractor = new ZipExtractor(new LocalFilesystem(new Filesystem()));
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();
        parent::tearDown();
    }

    #[Test]
    public function extractSuccessfully(): void
    {
        $zipPath = $this->createValidZip();
        $target = $this->tempDir . '/extracted';

        $this->extractor->extract($zipPath, $target);

        $this->assertFileExists($target . '/module.json');
        $this->assertStringContainsString('blog', file_get_contents($target . '/module.json'));
    }

    #[Test]
    public function extractToTempReturnsPathWithContents(): void
    {
        $zipPath = $this->createValidZip();

        $tempDir = $this->extractor->extractToTemp($zipPath);

        try {
            $this->assertDirectoryExists($tempDir);
            $this->assertFileExists($tempDir . '/module.json');
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    #[Test]
    public function extractThrowsOnMissingFile(): void
    {
        $this->expectException(ModuleArchiveException::class);

        $this->extractor->extract($this->tempDir . '/nonexistent.zip', $this->tempDir . '/target');
    }

    #[Test]
    public function extractThrowsOnCorruptedZip(): void
    {
        $corruptPath = $this->tempDir . '/corrupt.zip';
        file_put_contents($corruptPath, 'not a zip file');

        $this->expectException(ModuleArchiveException::class);

        $this->extractor->extract($corruptPath, $this->tempDir . '/target');
    }

    #[Test]
    public function extractThrowsOnPathTraversal(): void
    {
        $zipPath = $this->createZipWithTraversal();

        $this->expectException(ModuleArchiveException::class);
        $this->expectExceptionMessageMatches('/traversal/');

        $this->extractor->extract($zipPath, $this->tempDir . '/target');
    }

    #[Test]
    public function extractToTempCleansUpOnError(): void
    {
        $corruptPath = $this->tempDir . '/corrupt.zip';
        file_put_contents($corruptPath, 'not a zip');

        try {
            $this->extractor->extractToTemp($corruptPath);
            $this->fail('Expected ModuleArchiveException');
        } catch (ModuleArchiveException) {
            $tempDirs = glob(sys_get_temp_dir() . '/module_zip_*');
            foreach ($tempDirs as $dir) {
                if (is_dir($dir) && filemtime($dir) > time() - 5) {
                    $this->fail("Temporary directory [{$dir}] was not cleaned up.");
                }
            }
            $this->addToAssertionCount(1);
        }
    }

    private function createValidZip(): string
    {
        $zipPath = $this->tempDir . '/valid.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('module.json', json_encode(['meta' => ['name' => 'blog']]));
        $zip->close();

        return $zipPath;
    }

    private function createZipWithTraversal(): string
    {
        $zipPath = $this->tempDir . '/evil.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('../../../etc/passwd', 'evil');
        $zip->close();

        return $zipPath;
    }

}
