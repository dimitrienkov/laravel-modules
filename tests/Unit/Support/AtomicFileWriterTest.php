<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\AtomicWriteException;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AtomicFileWriterTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('atomic');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function it_writes_content_atomically(): void
    {
        $path = $this->tempDir . '/test.txt';

        (new AtomicFileWriter())->write($path, "hello world\n");

        self::assertSame("hello world\n", file_get_contents($path));
        self::assertFileDoesNotExist($path . '.lock');
    }

    #[Test]
    public function it_overwrites_existing_file(): void
    {
        $path = $this->tempDir . '/test.txt';
        $writer = new AtomicFileWriter();

        $writer->write($path, 'first');
        $writer->write($path, 'second');

        self::assertSame('second', file_get_contents($path));
    }

    #[Test]
    public function it_preserves_existing_file_permissions(): void
    {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, 'original');
        chmod($path, 0640);

        (new AtomicFileWriter())->write($path, 'updated');

        self::assertSame(0640, fileperms($path) & 0777);
    }

    #[Test]
    public function it_creates_parent_directory_if_missing(): void
    {
        $path = $this->tempDir . '/nested/dir/test.txt';

        (new AtomicFileWriter())->write($path, 'content');

        self::assertSame('content', file_get_contents($path));
    }

    #[Test]
    public function it_throws_when_target_path_is_a_directory(): void
    {
        $this->expectException(AtomicWriteException::class);
        $this->expectExceptionMessage('temporary file could not be renamed atomically');

        (new AtomicFileWriter())->write($this->tempDir, 'content');
    }

    #[Test]
    public function it_preserves_binary_content(): void
    {
        $path = $this->tempDir . '/binary.bin';
        $content = \chr(0) . \chr(255) . \chr(128);

        (new AtomicFileWriter())->write($path, $content);

        self::assertSame($content, file_get_contents($path));
    }
}
