<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\AtomicWriteException;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AtomicFileWriter::class)]
#[Group('support')]
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
    public function writesContentAtomically(): void
    {
        $path = $this->tempDir . '/test.txt';

        (new AtomicFileWriter())->write($path, "hello world\n");

        self::assertSame("hello world\n", file_get_contents($path));
        self::assertFileDoesNotExist($path . '.lock');
    }

    #[Test]
    public function overwritesExistingFile(): void
    {
        $path = $this->tempDir . '/test.txt';
        $writer = new AtomicFileWriter();

        $writer->write($path, 'first');
        $writer->write($path, 'second');

        self::assertSame('second', file_get_contents($path));
    }

    #[Test]
    public function preservesExistingFilePermissions(): void
    {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, 'original');
        chmod($path, 0640);

        (new AtomicFileWriter())->write($path, 'updated');

        self::assertSame(0640, fileperms($path) & 0777);
    }

    #[Test]
    public function createsParentDirectoryIfMissing(): void
    {
        $path = $this->tempDir . '/nested/dir/test.txt';

        (new AtomicFileWriter())->write($path, 'content');

        self::assertSame('content', file_get_contents($path));
    }

    #[Test]
    public function throwsWhenTargetPathIsADirectory(): void
    {
        $this->expectException(AtomicWriteException::class);
        $this->expectExceptionMessage('temporary file could not be renamed atomically');

        (new AtomicFileWriter())->write($this->tempDir, 'content');
    }

    #[Test]
    public function preservesBinaryContent(): void
    {
        $path = $this->tempDir . '/binary.bin';
        $content = \chr(0) . \chr(255) . \chr(128);

        (new AtomicFileWriter())->write($path, $content);

        self::assertSame($content, file_get_contents($path));
    }
}
