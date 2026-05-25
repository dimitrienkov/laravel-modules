<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\ModuleArchiveException;
use DimitrienkoV\LaravelModules\Support\ZipExtractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ZipExtractorTest extends TestCase
{
    private string $tempDir;

    private ZipExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/zip_extractor_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->extractor = new ZipExtractor();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
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
            $this->removeDir($tempDir);
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
