<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Exceptions\DirectoryOperationException;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleDirectoryOperationsTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    private ModuleDirectoryOperations $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/module_dir_ops_' . uniqid();
        $this->filesystem->makeDirectory($this->tempDir . '/app/Modules', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/backups', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/source', 0755, true);

        $config = new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                    'backup' => $this->tempDir . '/backups',
                ],
            ],
        ]);

        $paths = new ModuleDirectoryPaths(
            config: $config,
            basePath: $this->tempDir,
            appPath: $this->tempDir . '/app',
        );

        $this->ops = new ModuleDirectoryOperations($this->filesystem, $paths);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function copyDirectorySuccess(): void
    {
        $source = $this->tempDir . '/source/blog';
        $target = $this->tempDir . '/app/Modules/Blog';
        $this->filesystem->makeDirectory($source, 0755, true);
        file_put_contents($source . '/module.json', '{}');

        $this->ops->copyDirectory($source, $target);

        $this->assertFileExists($target . '/module.json');
    }

    #[Test]
    public function copyDirectoryThrowsOnFailure(): void
    {
        $this->expectException(DirectoryOperationException::class);

        $this->ops->copyDirectory('/nonexistent/path', $this->tempDir . '/target');
    }

    #[Test]
    public function replaceDirectoryCreatesBackupAndCopiesSource(): void
    {
        $target = $this->tempDir . '/app/Modules/Blog';
        $source = $this->tempDir . '/source/blog_v2';
        $this->filesystem->makeDirectory($target, 0755, true);
        file_put_contents($target . '/old.txt', 'old');
        $this->filesystem->makeDirectory($source, 0755, true);
        file_put_contents($source . '/new.txt', 'new');

        $backupPath = $this->ops->replaceDirectoryWithBackup($target, $source, 'blog');

        $this->assertFileExists($target . '/new.txt');
        $this->assertFileDoesNotExist($target . '/old.txt');
        $this->assertFileExists($backupPath . '/old.txt');
    }

    #[Test]
    public function moveToBackupMovesDirectory(): void
    {
        $target = $this->tempDir . '/app/Modules/Blog';
        $this->filesystem->makeDirectory($target, 0755, true);
        file_put_contents($target . '/module.json', '{}');

        $backupPath = $this->ops->moveToBackup($target, 'blog');

        $this->assertDirectoryDoesNotExist($target);
        $this->assertFileExists($backupPath . '/module.json');
    }

    #[Test]
    public function deleteDirectoryRemovesTarget(): void
    {
        $target = $this->tempDir . '/app/Modules/Blog';
        $this->filesystem->makeDirectory($target, 0755, true);
        file_put_contents($target . '/file.txt', 'data');

        $this->ops->deleteDirectory($target, 'blog');

        $this->assertDirectoryDoesNotExist($target);
    }

    #[Test]
    public function deleteDirectoryQuietlyIsIdempotent(): void
    {
        $path = $this->tempDir . '/nonexistent';

        $this->ops->deleteDirectoryQuietly($path);
        $this->assertDirectoryDoesNotExist($path);

        $this->filesystem->makeDirectory($path, 0755, true);
        file_put_contents($path . '/file.txt', 'data');

        $this->ops->deleteDirectoryQuietly($path);
        $this->assertDirectoryDoesNotExist($path);
    }
}
