<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AtomicJsonWriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-writer-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_writes_pretty_json_atomically(): void
    {
        $path = $this->tempDir . '/module.json';

        (new AtomicJsonWriter())->write($path, [
            'meta' => [
                'name' => 'blog',
            ],
        ]);

        $contents = (string) file_get_contents($path);

        self::assertStringContainsString(PHP_EOL, $contents);
        self::assertSame([
            'meta' => [
                'name' => 'blog',
            ],
        ], json_decode($contents, true, flags: JSON_THROW_ON_ERROR));
        self::assertFileDoesNotExist($path . '.lock');
    }

    #[Test]
    public function it_throws_when_target_path_cannot_be_replaced(): void
    {
        $this->expectException(ManifestWriteException::class);
        $this->expectExceptionMessage('temporary file could not be renamed atomically');

        (new AtomicJsonWriter())->write($this->tempDir, ['meta' => ['name' => 'blog']]);
    }

    #[Test]
    public function it_overwrites_existing_file(): void
    {
        $path = $this->tempDir . '/module.json';
        $writer = new AtomicJsonWriter();

        $writer->write($path, ['meta' => ['name' => 'blog']]);
        $writer->write($path, ['meta' => ['name' => 'shop']]);

        $contents = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['meta' => ['name' => 'shop']], $contents);
    }

    #[Test]
    public function it_preserves_unicode_characters(): void
    {
        $path = $this->tempDir . '/module.json';
        (new AtomicJsonWriter())->write($path, ['meta' => ['description' => 'Блог с комментариями 🔥']]);

        $contents = (string) file_get_contents($path);

        self::assertStringContainsString('Блог с комментариями 🔥', $contents);
        self::assertStringNotContainsString('\u', $contents);
    }

    #[Test]
    public function it_reports_target_path_when_json_encode_fails(): void
    {
        $path = $this->tempDir . '/module.json';
        $handle = fopen($this->tempDir . '/resource.txt', 'wb');

        self::assertIsResource($handle);

        try {
            $this->expectException(ManifestWriteException::class);
            $this->expectExceptionMessage("Unable to write module manifest [{$path}]");

            (new AtomicJsonWriter())->write($path, ['invalid' => $handle]);
        } finally {
            fclose($handle);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}
